import logging
import re
from datetime import datetime, timedelta

from aiogram import F, Router, types
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext

from handlers.auth import require_auth
from services import api_client
from services.geocoder import validate_address
from services.order_service import create_order, format_order
from states import OrderState
from storage import contacts_db, employees_db, orders_db, users_db
from validators import is_forbidden_order_time, normalize_phone

logger = logging.getLogger(__name__)


router = Router()


@router.message(Command("order"))
@require_auth
async def cmd_order(message: types.Message, state: FSMContext):
    keyboard = types.InlineKeyboardMarkup(
        inline_keyboard=[
            [types.InlineKeyboardButton(text="Да", callback_data="order_yes")],
            [types.InlineKeyboardButton(text="Нет", callback_data="order_no")]
        ]
    )
    await message.answer("Нужно ли вам такси на сегодня?", reply_markup=keyboard)
    await state.set_state(OrderState.waiting_for_choice)


@router.callback_query(OrderState.waiting_for_choice)
async def process_order_choice(callback: types.CallbackQuery, state: FSMContext):
    if callback.data == "order_no":
        await callback.message.edit_text("✅ Хорошо, заявка не требуется.")
        await state.clear()
    elif callback.data == "order_yes":
        await callback.message.edit_text("На какое время вам нужно такси? (В формате ЧЧ:ММ, например 22:15):")
        await state.set_state(OrderState.waiting_for_time)
    await callback.answer()


@router.message(OrderState.waiting_for_time)
async def process_order_time(message: types.Message, state: FSMContext):
    time_str = message.text.strip()
    try:
        order_time = datetime.strptime(time_str, "%H:%M").time()
        if is_forbidden_order_time(order_time):
            await message.answer(
                "❌ Такси нельзя заказать на время с 06:01 до 21:59.\n"
                "Введите время с 22:00 до 06:00:"
            )
            return
        await state.update_data(time=time_str)
        user_id = str(message.from_user.id)
        employee_id = users_db[user_id]
        if employee_id in contacts_db:
            await state.update_data(
                address=contacts_db[employee_id]["address"],
                phone=contacts_db[employee_id]["phone"]
            )
            data = await state.get_data()
            keyboard = types.InlineKeyboardMarkup(
                inline_keyboard=[
                    [types.InlineKeyboardButton(text="✅ Использовать сохраненные данные", callback_data="use_saved")],
                    [types.InlineKeyboardButton(text="✏️ Ввести новый адрес", callback_data="new_address")]
                ]
            )
            await message.answer(
                f"Использовать сохраненные данные?\n\n"
                f"Адрес: {data['address']}\n"
                f"Телефон: {data['phone']}",
                reply_markup=keyboard
            )
        else:
            await message.answer("Введите ваш адрес (например: ул. Ленина, д. 12):")
            await state.set_state(OrderState.waiting_for_address)
    except ValueError:
        await message.answer("❌ Неверный формат времени. Введите время в формате ЧЧ:ММ (например, 22:15):")


@router.callback_query(OrderState.waiting_for_time)
async def process_data_choice(callback: types.CallbackQuery, state: FSMContext):
    if callback.data == "use_saved":
        await process_order_confirmation(callback, state)
    elif callback.data == "new_address":
        await callback.message.edit_text("Введите новый адрес:")
        await state.set_state(OrderState.waiting_for_address)
    await callback.answer()


@router.message(OrderState.waiting_for_address)
async def process_new_address_order(message: types.Message, state: FSMContext):
    address = message.text.strip()
    is_valid, result = validate_address(address)
    if not is_valid:
        await message.answer(f"❌ {result}\nПожалуйста, введите адрес снова:")
        return
    if result != address:
        await state.update_data(corrected_address=result, original_address=address)
        keyboard = types.InlineKeyboardMarkup(
            inline_keyboard=[
                [types.InlineKeyboardButton(text="✅ Использовать уточненный адрес",
                                            callback_data="use_corrected_order")],
                [types.InlineKeyboardButton(text="✏️ Ввести заново", callback_data="reject_corrected_order")]
            ]
        )
        await message.answer(
            f"🔍 Геокодер уточнил адрес:\n{result}\n\nВыберите действие:",
            reply_markup=keyboard
        )
        await state.set_state(OrderState.waiting_for_address_confirm)
    else:
        await state.update_data(address=address)
        await message.answer("Введите ваш номер телефона:")
        await state.set_state(OrderState.waiting_for_phone)


@router.callback_query(
    OrderState.waiting_for_address_confirm,
    F.data.in_(["use_corrected_order", "reject_corrected_order"])
)
async def process_address_confirm_order(callback: types.CallbackQuery, state: FSMContext):
    data = await state.get_data()
    if callback.data == "use_corrected_order":
        address = data["corrected_address"]
        await state.update_data(address=address)
        await callback.message.edit_text(f"✅ Адрес сохранен: {address}")
        await callback.message.answer("Введите ваш номер телефона:")
        await state.set_state(OrderState.waiting_for_phone)
    else:
        await callback.message.edit_text("Введите адрес снова:")
        await state.set_state(OrderState.waiting_for_address)
    await callback.answer()


@router.message(OrderState.waiting_for_phone)
async def process_new_phone_order(message: types.Message, state: FSMContext):
    raw_phone = message.text.strip()
    normalized_phone = normalize_phone(raw_phone)
    if not re.match(r"^\+7\d{10}$", normalized_phone):
        await message.answer("❌ Неверный формат номера. Введите номер в формате 89131234567 или +79123456789:")
        return
    await state.update_data(phone=normalized_phone)
    await process_order_confirmation_message(message, state)


async def process_order_confirmation_message(message: types.Message, state: FSMContext):
    user_id_str = str(message.from_user.id)
    await process_order_confirmation_logic(message, state, user_id_str)


async def process_order_confirmation(callback: types.CallbackQuery, state: FSMContext):
    user_id_str = str(callback.from_user.id)
    await process_order_confirmation_logic(callback, state, user_id_str, is_callback=True)


async def process_order_confirmation_logic(source, state: FSMContext, user_id_str: str, is_callback=False):
    data = await state.get_data()
    employee_id = users_db[user_id_str]
    employee_name = employees_db[employee_id]
    order_text = f"{employee_name}\n{data['address']}\n{data['phone']}\n{data['time']}"
    keyboard = types.InlineKeyboardMarkup(
        inline_keyboard=[
            [types.InlineKeyboardButton(text="✅ Подтвердить", callback_data="confirm_order")],
            [types.InlineKeyboardButton(text="❌ Отменить", callback_data="cancel_order")]
        ]
    )
    if is_callback:
        await source.message.edit_text(
            f"Проверьте данные заявки:\n\n{order_text}\n\nВсе верно?",
            reply_markup=keyboard
        )
    else:
        await source.answer(
            f"Проверьте данные заявки:\n\n{order_text}\n\nВсе верно?",
            reply_markup=keyboard
        )
    await state.set_state(OrderState.waiting_for_confirmation)


@router.callback_query(OrderState.waiting_for_confirmation)
async def process_confirmation(callback: types.CallbackQuery, state: FSMContext):
    if callback.data == "cancel_order":
        await callback.message.edit_text("❌ Заявка отменена")
        await state.clear()
        return
    data = await state.get_data()
    user_id = str(callback.from_user.id)
    employee_id = users_db[user_id]
    employee_name = employees_db[employee_id]

    if api_client.is_configured():
        try:
            time_obj = datetime.strptime(data["time"], "%H:%M")
            now = datetime.now()
            order_dt = now.replace(hour=time_obj.hour, minute=time_obj.minute, second=0, microsecond=0)
            if order_dt <= now:
                order_dt += timedelta(days=1)
            date_time_str = order_dt.strftime("%Y-%m-%d %H:%M:%S")
            result = await api_client.create_request(
                telegram_id=user_id,
                full_name=employee_name,
                phone=data["phone"],
                address_raw=data["address"],
                date_time=date_time_str,
            )
            await callback.message.edit_text(
                f"✅ Заявка #{result.get('id')} отправлена руководителю на согласование!\n\n"
                f"ФИО: {result.get('full_name')}\n"
                f"Адрес: {result.get('address_raw')}\n"
                f"Телефон: {result.get('phone')}\n"
                f"Время: {result.get('date_time', '')[:16]}"
            )
            await state.clear()
            return
        except api_client.ApiError as e:
            logger.warning("create_request failed (%s): %s", e.status_code, e.message)
            await callback.message.edit_text(f"❌ Ошибка при создании заявки: {e.message}")
            await state.clear()
            return

    order = create_order(
        employee_id,
        employee_name,
        user_id,
        data["address"],
        data["phone"],
        data["time"]
    )
    await callback.message.edit_text(
        "✅ Заявка отправлена руководителю на согласование!\n\n"
        f"{format_order(order, include_manager_info=False)}"
    )
    await state.clear()


STATUS_EMOJI = {
    "pending": "⏳",
    "approved": "✅",
    "rejected": "❌",
    "cancelled": "🚫",
    "expired": "⌛",
}

STATUS_TEXT_API = {
    1: ("⏳", "На согласовании"),
    2: ("✅", "Одобрена"),
    3: ("❌", "Отклонена"),
    4: ("🚫", "Отменена"),
    5: ("⌛", "Просрочена"),
}


@router.message(Command("my_orders"))
@require_auth
async def cmd_my_orders(message: types.Message):
    user_id = str(message.from_user.id)
    employee_id = users_db[user_id]
    employee_name = employees_db.get(employee_id, "Сотрудник")

    if api_client.is_configured():
        try:
            data = await api_client.get_my_requests(user_id)
            temp_requests = data.get("temp_requests", [])
            final_requests = data.get("requests", [])
            all_items = list(temp_requests) + list(final_requests)
            if not all_items:
                await message.answer("У вас еще нет заявок.")
                return
            text = f"📋 Ваши заявки: {employee_name}\n"
            for item in all_items[:20]:
                status_code = item.get("status")
                emoji, status_label = STATUS_TEXT_API.get(status_code, ("❓", str(status_code)))
                text += (
                    f"\n{'─' * 20}\n"
                    f"№{item.get('id')} {emoji} {status_label}\n"
                    f"Адрес: {item.get('address_raw', '—')}\n"
                    f"Время: {str(item.get('date_time', '—'))[:16]}\n"
                )
                if item.get("rejection_reason"):
                    text += f"Причина: {item['rejection_reason']}\n"
            await message.answer(text.strip())
            return
        except api_client.ApiError as e:
            logger.warning("get_my_requests failed: %s", e)

    user_orders = orders_db.get(employee_id, [])
    if not user_orders:
        await message.answer("У вас еще нет сохраненных заявок.")
        return

    sorted_orders = sorted(user_orders, key=lambda item: item.get("timestamp", ""), reverse=True)
    messages = [f"📋 Ваши заявки: {employee_name}\n"]
    for index, order in enumerate(sorted_orders, 1):
        order_block = f"\nЗаявка {index}\n{format_order(order)}"
        if order.get("timestamp"):
            order_block += f"\nСоздана: {order['timestamp']}"
        if order.get("decision_timestamp"):
            order_block += f"\nРассмотрена: {order['decision_timestamp']}"
        order_block += "\n"
        if len(messages[-1]) + len(order_block) > 3900:
            messages.append(order_block.strip())
        else:
            messages[-1] += order_block
    for response in messages:
        await message.answer(response.strip())
