import logging
import re
from functools import wraps

from aiogram import F, Router, types
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext

from config import CONTACTS_FILE, ORDERS_FILE, USERS_FILE
from services import api_client
from services.geocoder import validate_address
from services.roles import is_admin, is_manager
from states import AuthState
from storage import (
    contacts_db,
    employees_db,
    get_employee_data,
    orders_db,
    save_data,
    users_db,
)
from validators import normalize_phone

logger = logging.getLogger(__name__)


router = Router()


def get_available_commands_text(telegram_id):
    commands = [
        "📱 /app - открыть Mini App",
        "🚖 /order - оставить заявку на такси",
        "⏰ /reminder - настроить напоминание",
        "📋 /my_orders - мои заявки и их статус",
        "🔓 /logout - выйти из аккаунта",
    ]
    if is_manager(telegram_id):
        commands.append("✅ /manager - согласование заявок")
    if is_admin(telegram_id):
        commands.append("🛠 /admin - админ-панель")
    return "Доступные команды:\n" + "\n".join(commands)


@router.message(Command("start"))
async def cmd_start(message: types.Message, state: FSMContext):
    user_id = str(message.from_user.id)
    if user_id in users_db:
        employee_id = users_db[user_id]
        employee_data = get_employee_data(employee_id)
        if employee_data:
            await message.answer(
                f"👋 С возвращением, {employee_data['full_name']}!\n"
                f"Ваш табельный номер: {employee_id}\n\n"
                f"{get_available_commands_text(user_id)}"
            )
        return
    await message.answer(
        "🚖 Добро пожаловать в бот корпоративного такси!\n\n"
        "Для авторизации введите ваш табельный номер:"
    )
    await state.set_state(AuthState.waiting_for_id)


@router.message(AuthState.waiting_for_id)
async def process_employee_id(message: types.Message, state: FSMContext):
    employee_id = message.text.strip()
    user_id = str(message.from_user.id)
    if employee_id not in employees_db:
        await message.answer("❌ Табельный номер не найден. Обратитесь к администратору.")
        await state.clear()
        return
    for tg_id, emp_id in users_db.items():
        if emp_id == employee_id and tg_id != user_id:
            await message.answer("❌ Этот табельный номер уже привязан к другому аккаунту Telegram.")
            await state.clear()
            return
    await state.update_data(employee_id=employee_id)
    employee_name = employees_db[employee_id]
    await message.answer(
        f"✅ Табельный номер подтвержден!\n"
        f"Добро пожаловать, {employee_name}!\n\n"
        f"Для завершения регистрации введите ваш адрес\n"
        f"(например: ул. Ленина, д. 12):"
    )
    await state.set_state(AuthState.waiting_for_address)


@router.message(AuthState.waiting_for_address)
async def process_address_auth(message: types.Message, state: FSMContext):
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
                                            callback_data="use_corrected_auth")],
                [types.InlineKeyboardButton(text="✏️ Ввести заново", callback_data="reject_corrected_auth")]
            ]
        )
        await message.answer(
            f"🔍 Геокодер уточнил адрес:\n{result}\n\nВыберите действие:",
            reply_markup=keyboard
        )
        await state.set_state(AuthState.waiting_for_address_confirm)
    else:
        await state.update_data(address=address)
        await message.answer("Введите ваш номер телефона:")
        await state.set_state(AuthState.waiting_for_phone)


@router.callback_query(AuthState.waiting_for_address_confirm, F.data.in_(["use_corrected_auth", "reject_corrected_auth"]))
async def process_address_confirm_auth(callback: types.CallbackQuery, state: FSMContext):
    data = await state.get_data()
    if callback.data == "use_corrected_auth":
        address = data["corrected_address"]
        await state.update_data(address=address)
        await callback.message.edit_text(f"✅ Адрес сохранен: {address}")
        await callback.message.answer("Введите ваш номер телефона:")
        await state.set_state(AuthState.waiting_for_phone)
    else:
        await callback.message.edit_text("Введите адрес снова:")
        await state.set_state(AuthState.waiting_for_address)
    await callback.answer()


@router.message(AuthState.waiting_for_phone)
async def process_phone(message: types.Message, state: FSMContext):
    raw_phone = message.text.strip()
    normalized_phone = normalize_phone(raw_phone)
    if not re.match(r"^\+7\d{10}$", normalized_phone):
        await message.answer("❌ Неверный формат номера. Введите номер в формате 89131234567 или +79123456789:")
        return
    data = await state.get_data()
    employee_id = data["employee_id"]
    employee_name = employees_db[employee_id]
    user_id = str(message.from_user.id)

    if api_client.is_configured():
        try:
            await api_client.link_telegram(employee_id, user_id)
        except api_client.ApiError as e:
            if e.status_code == 409:
                await message.answer("❌ Этот табельный номер уже привязан к другому аккаунту Telegram.")
                await state.clear()
                return
            logger.warning("link_telegram failed: %s", e)

    users_db[user_id] = employee_id
    save_data(USERS_FILE, users_db)
    contacts_db[employee_id] = {
        "address": data["address"],
        "phone": normalized_phone
    }
    save_data(CONTACTS_FILE, contacts_db)
    if employee_id not in orders_db:
        orders_db[employee_id] = []
        save_data(ORDERS_FILE, orders_db)
    await state.clear()
    await message.answer(
        f"✅ Авторизация завершена!\n\n"
        f"📋 Ваши данные:\n"
        f"ФИО: {employee_name}\n"
        f"Табельный: {employee_id}\n"
        f"Адрес: {data['address']}\n"
        f"Телефон: {normalized_phone}\n\n"
        f"{get_available_commands_text(user_id)}"
    )


@router.message(Command("logout"))
async def cmd_logout(message: types.Message):
    user_id = str(message.from_user.id)
    if user_id in users_db:
        if api_client.is_configured():
            try:
                await api_client.unlink_telegram(user_id)
            except api_client.ApiError as e:
                logger.warning("unlink_telegram failed: %s", e)
        del users_db[user_id]
        save_data(USERS_FILE, users_db)
        await message.answer("✅ Вы вышли из аккаунта. Для входа используйте /start")
    else:
        await message.answer("Вы не авторизованы. Используйте /start")


def require_auth(func):
    @wraps(func)
    async def wrapper(message: types.Message, *args, **kwargs):
        user_id = str(message.from_user.id)
        if user_id not in users_db:
            await message.answer("❌ Вы не авторизованы. Используйте /start")
            return
        filtered_kwargs = {}
        if "state" in kwargs:
            filtered_kwargs["state"] = kwargs["state"]
        return await func(message, *args, **filtered_kwargs)

    return wrapper
