import logging
from datetime import datetime

from aiogram import F, Router, types
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext

from config import REMINDERS_FILE
from handlers.auth import require_auth
from loader import bot, scheduler
from states import OrderState, ReminderState
from storage import employees_db, reminders_db, save_data, users_db


logger = logging.getLogger(__name__)
router = Router()

REMINDER_DAY_OPTIONS = [
    ("mon", "Пн", "понедельник"),
    ("tue", "Вт", "вторник"),
    ("wed", "Ср", "среда"),
    ("thu", "Чт", "четверг"),
    ("fri", "Пт", "пятница"),
    ("sat", "Сб", "суббота"),
    ("sun", "Вс", "воскресенье"),
]
REMINDER_WEEKDAYS = ["mon", "tue", "wed", "thu", "fri"]


def build_reminder_schedule_keyboard():
    return types.InlineKeyboardMarkup(
        inline_keyboard=[
            [types.InlineKeyboardButton(text="Каждый день", callback_data="reminder_schedule_daily")],
            [types.InlineKeyboardButton(text="5/2 (Пн-Пт)", callback_data="reminder_schedule_weekdays")],
            [types.InlineKeyboardButton(text="Выбрать дни", callback_data="reminder_schedule_custom")]
        ]
    )


def build_custom_days_keyboard(selected_days):
    selected_days = set(selected_days)
    day_buttons = [
        types.InlineKeyboardButton(
            text=f"{'✅' if code in selected_days else '⬜'} {short_name}",
            callback_data=f"reminder_day_{code}"
        )
        for code, short_name, _ in REMINDER_DAY_OPTIONS
    ]
    keyboard = [day_buttons[i:i + 2] for i in range(0, len(day_buttons), 2)]
    keyboard.append([types.InlineKeyboardButton(text="✅ Готово", callback_data="reminder_custom_done")])
    keyboard.append([types.InlineKeyboardButton(text="↩️ Назад", callback_data="reminder_custom_back")])
    return types.InlineKeyboardMarkup(inline_keyboard=keyboard)


def format_reminder_days(days):
    ordered_days = [
        full_name
        for code, _, full_name in REMINDER_DAY_OPTIONS
        if code in days
    ]
    return ", ".join(ordered_days)


def get_reminder_schedule_text(reminder_data):
    schedule_type = reminder_data.get("schedule_type", "daily")
    if schedule_type == "weekdays":
        return "5/2 (понедельник-пятница)"
    if schedule_type == "custom":
        days_text = format_reminder_days(reminder_data.get("days", []))
        return f"выбранные дни: {days_text}"
    return "каждый день"


def add_or_update_reminder_job(employee_id):
    job_id = f"reminder_{employee_id}"
    try:
        scheduler.remove_job(job_id)
    except Exception:
        pass

    reminder_data = reminders_db.get(employee_id)
    if not reminder_data or not reminder_data.get("active", False):
        return

    reminder_time = datetime.strptime(reminder_data["time"], "%H:%M").time()
    schedule_type = reminder_data.get("schedule_type", "daily")
    cron_kwargs = {}

    if schedule_type == "weekdays":
        cron_kwargs["day_of_week"] = "mon-fri"
    elif schedule_type == "custom":
        selected_days = [
            code
            for code, _, _ in REMINDER_DAY_OPTIONS
            if code in reminder_data.get("days", [])
        ]
        if not selected_days:
            logger.warning(f"Для напоминания {employee_id} не выбраны дни")
            return
        cron_kwargs["day_of_week"] = ",".join(selected_days)

    scheduler.add_job(
        send_reminder,
        "cron",
        hour=reminder_time.hour,
        minute=reminder_time.minute,
        id=job_id,
        args=[employee_id],
        **cron_kwargs
    )
    if not scheduler.running:
        scheduler.start()


async def save_reminder_settings(source_message, state, employee_id, user_id, time_str, schedule_type, days=None):
    days = days or []
    reminders_db[employee_id] = {
        "time": time_str,
        "telegram_id": user_id,
        "active": True,
        "schedule_type": schedule_type,
        "days": days
    }
    save_data(REMINDERS_FILE, reminders_db)
    add_or_update_reminder_job(employee_id)

    schedule_text = get_reminder_schedule_text(reminders_db[employee_id])
    await source_message.edit_text(
        f"✅ Напоминание установлено на {time_str}\n"
        f"Расписание: {schedule_text}"
    )
    await state.clear()


def load_active_reminders():
    for employee_id, reminder_data in reminders_db.items():
        if reminder_data.get("active", False):
            try:
                add_or_update_reminder_job(employee_id)
            except Exception as e:
                logger.error(f"Ошибка при загрузке напоминания {employee_id}: {e}")
    if not scheduler.running:
        scheduler.start()


@router.message(Command("reminder"))
@require_auth
async def cmd_reminder(message: types.Message, state: FSMContext):
    await message.answer("На какое время установить напоминание? (В формате ЧЧ:ММ, например 17:00):")
    await state.set_state(ReminderState.waiting_for_time)


@router.message(ReminderState.waiting_for_time)
async def process_reminder_time(message: types.Message, state: FSMContext):
    time_str = message.text.strip()
    try:
        datetime.strptime(time_str, "%H:%M")
        await state.update_data(time=time_str)
        await message.answer(
            "Выберите расписание напоминаний:",
            reply_markup=build_reminder_schedule_keyboard()
        )
        await state.set_state(ReminderState.waiting_for_schedule)
    except ValueError:
        await message.answer("❌ Неверный формат времени. Введите время в формате ЧЧ:ММ (например, 17:00):")


@router.callback_query(
    ReminderState.waiting_for_schedule,
    F.data.in_(["reminder_schedule_daily", "reminder_schedule_weekdays", "reminder_schedule_custom"])
)
async def process_reminder_schedule(callback: types.CallbackQuery, state: FSMContext):
    data = await state.get_data()
    time_str = data["time"]
    user_id = str(callback.from_user.id)
    employee_id = users_db[user_id]

    if callback.data == "reminder_schedule_daily":
        await save_reminder_settings(callback.message, state, employee_id, user_id, time_str, "daily")
    elif callback.data == "reminder_schedule_weekdays":
        await save_reminder_settings(
            callback.message,
            state,
            employee_id,
            user_id,
            time_str,
            "weekdays",
            REMINDER_WEEKDAYS
        )
    else:
        await state.update_data(selected_days=[])
        await callback.message.edit_text(
            "Выберите дни недели:",
            reply_markup=build_custom_days_keyboard([])
        )
        await state.set_state(ReminderState.waiting_for_custom_days)
    await callback.answer()


@router.callback_query(ReminderState.waiting_for_custom_days, F.data.startswith("reminder_day_"))
async def process_custom_reminder_day(callback: types.CallbackQuery, state: FSMContext):
    day_code = callback.data.replace("reminder_day_", "", 1)
    valid_days = {code for code, _, _ in REMINDER_DAY_OPTIONS}
    if day_code not in valid_days:
        await callback.answer()
        return

    data = await state.get_data()
    selected_days = set(data.get("selected_days", []))
    if day_code in selected_days:
        selected_days.remove(day_code)
    else:
        selected_days.add(day_code)

    ordered_selected_days = [
        code
        for code, _, _ in REMINDER_DAY_OPTIONS
        if code in selected_days
    ]
    await state.update_data(selected_days=ordered_selected_days)
    await callback.message.edit_reply_markup(
        reply_markup=build_custom_days_keyboard(ordered_selected_days)
    )
    await callback.answer()


@router.callback_query(
    ReminderState.waiting_for_custom_days,
    F.data.in_(["reminder_custom_done", "reminder_custom_back"])
)
async def process_custom_reminder_action(callback: types.CallbackQuery, state: FSMContext):
    if callback.data == "reminder_custom_back":
        await callback.message.edit_text(
            "Выберите расписание напоминаний:",
            reply_markup=build_reminder_schedule_keyboard()
        )
        await state.set_state(ReminderState.waiting_for_schedule)
        await callback.answer()
        return

    data = await state.get_data()
    selected_days = data.get("selected_days", [])
    if not selected_days:
        await callback.answer("Выберите хотя бы один день", show_alert=True)
        return

    time_str = data["time"]
    user_id = str(callback.from_user.id)
    employee_id = users_db[user_id]
    await save_reminder_settings(
        callback.message,
        state,
        employee_id,
        user_id,
        time_str,
        "custom",
        selected_days
    )
    await callback.answer()


async def send_reminder(employee_id: str):
    try:
        if employee_id not in reminders_db:
            return
        reminder_data = reminders_db[employee_id]
        if not reminder_data.get("active", False):
            return
        telegram_id = reminder_data.get("telegram_id")
        if not telegram_id:
            return
        employee_name = employees_db.get(employee_id, "Сотрудник")
        keyboard = types.InlineKeyboardMarkup(
            inline_keyboard=[
                [types.InlineKeyboardButton(text="🚖 Оставить заявку", callback_data="remind_order")],
                [types.InlineKeyboardButton(text="❌ Такси не нужно", callback_data="remind_no")]
            ]
        )
        await bot.send_message(
            chat_id=int(telegram_id),
            text=f"⏰ Напоминание!\n{employee_name}, не забудьте оставить заявку на такси на сегодня!",
            reply_markup=keyboard
        )
    except Exception as e:
        logger.error(f"Ошибка при отправке напоминания: {e}")


@router.callback_query(F.data.startswith("remind_"))
async def process_reminder_action(callback: types.CallbackQuery, state: FSMContext):
    user_id = str(callback.from_user.id)
    if user_id not in users_db:
        await callback.message.edit_text("❌ Вы не авторизованы. Используйте /start")
        await callback.answer()
        return
    if callback.data == "remind_no":
        await callback.message.edit_text("✅ Хорошо, заявка не требуется.")
    elif callback.data == "remind_order":
        await callback.message.edit_text("На какое время вам нужно такси? (В формате ЧЧ:ММ, например 22:15):")
        await state.set_state(OrderState.waiting_for_time)
    await callback.answer()
