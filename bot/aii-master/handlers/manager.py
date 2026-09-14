import logging
import re
from datetime import datetime

from aiogram import F, Router, types
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext

from loader import bot
from services import api_client
from services.geocoder import validate_address
from services.order_service import (
    edit_order,
    find_order,
    format_order,
    get_employee_telegram_id,
    list_orders,
    set_order_status,
)
from services.roles import get_manager_name, is_manager
from states import ManagerState
from storage import users_db
from validators import is_forbidden_order_time, normalize_phone


logger = logging.getLogger(__name__)
router = Router()


def get_order_keyboard(order_id):
    return types.InlineKeyboardMarkup(
        inline_keyboard=[
            [
                types.InlineKeyboardButton(text="✅ Одобрить", callback_data=f"mgr_approve_{order_id}"),
                types.InlineKeyboardButton(text="✏️ Редактировать", callback_data=f"mgr_edit_{order_id}"),
            ],
            [types.InlineKeyboardButton(text="❌ Отклонить", callback_data=f"mgr_reject_{order_id}")],
        ]
    )


async def notify_order_owner(order, text):
    telegram_id = order.get("telegram_id") or get_employee_telegram_id(order.get("employee_id"))
    if not telegram_id:
        logger.warning("Не удалось отправить уведомление: у заявки %s нет Telegram ID", order.get("id"))
        return
    try:
        await bot.send_message(chat_id=int(telegram_id), text=text)
    except Exception as e:
        logger.error("Ошибка отправки уведомления по заявке %s: %s", order.get("id"), e)


def parse_manager_action(callback_data):
    prefix = "mgr_"
    if not callback_data.startswith(prefix):
        return None, None
    action, _, order_id = callback_data[len(prefix):].partition("_")
    return action, order_id


def is_manager_order_available(order):
    return order and order.get("status") == "pending"


def _format_api_request(req: dict) -> str:
    STATUS_LABELS = {1: "На согласовании", 2: "Одобрена", 3: "Отклонена", 4: "Отменена", 5: "Просрочена"}
    return (
        f"№{req.get('id')} | {STATUS_LABELS.get(req.get('status'), '?')}\n"
        f"Сотрудник: {req.get('full_name', '—')}\n"
        f"Адрес: {req.get('address_raw', '—')}\n"
        f"Телефон: {req.get('phone', '—')}\n"
        f"Время: {str(req.get('date_time', '—'))[:16]}"
    )


@router.message(Command("manager"))
async def cmd_manager(message: types.Message):
    if not is_manager(message.from_user.id):
        await message.answer("❌ Доступ запрещен")
        return

    user_id = str(message.from_user.id)

    if api_client.is_configured():
        try:
            data = await api_client.get_pending_requests(user_id)
            pending = [r for r in data.get("temp_requests", []) if r.get("status") == 1]
            if not pending:
                await message.answer("✅ Заявок на согласовании нет")
                return
            await message.answer(f"📋 Заявки на согласовании: {len(pending)}")
            for req in pending[:20]:
                await message.answer(
                    _format_api_request(req),
                    reply_markup=get_order_keyboard(req["id"])
                )
            if len(pending) > 20:
                await message.answer("Показаны первые 20 заявок.")
            return
        except api_client.ApiError as e:
            logger.warning("get_pending_requests failed: %s", e)

    pending_orders = list_orders(status="pending")
    if not pending_orders:
        await message.answer("✅ Заявок на согласовании нет")
        return

    await message.answer(f"📋 Заявки на согласовании: {len(pending_orders)}")
    for order in pending_orders[:20]:
        await message.answer(
            format_order(order),
            reply_markup=get_order_keyboard(order["id"])
        )

    if len(pending_orders) > 20:
        await message.answer("Показаны первые 20 заявок. Повторите команду позже для следующей обработки.")


@router.callback_query(F.data.startswith("mgr_"))
async def process_manager_action(callback: types.CallbackQuery, state: FSMContext):
    if not is_manager(callback.from_user.id):
        await callback.answer("Доступ запрещен", show_alert=True)
        return

    action, order_id = parse_manager_action(callback.data)
    employee_id, order = find_order(order_id)
    if not is_manager_order_available(order):
        await callback.answer("Заявка уже обработана", show_alert=True)
        if order:
            await callback.message.edit_text(format_order(order))
        return

    manager_name = get_manager_name(callback.from_user.id) or "Руководитель"

    if action == "approve":
        if api_client.is_configured():
            try:
                result = await api_client.review_request(
                    telegram_id=callback.from_user.id,
                    temp_request_id=int(order_id),
                    action="approve",
                )
                await callback.message.edit_text(
                    f"✅ Заявка одобрена\n\n{_format_api_request(result)}"
                )
                owner_tg = order.get("telegram_id") or get_employee_telegram_id(order.get("employee_id"))
                if owner_tg:
                    try:
                        await bot.send_message(int(owner_tg), f"✅ Ваша заявка на такси одобрена.\n\n{_format_api_request(result)}")
                    except Exception as e:
                        logger.error("Ошибка уведомления: %s", e)
                await callback.answer()
                return
            except api_client.ApiError as e:
                logger.warning("review_request approve failed: %s", e)
        set_order_status(order, "approved", callback.from_user.id, manager_name)
        await callback.message.edit_text(f"✅ Заявка одобрена\n\n{format_order(order)}")
        await notify_order_owner(
            order,
            f"✅ Ваша заявка на такси одобрена.\n\n{format_order(order, include_manager_info=False)}"
        )
        await callback.answer()
        return

    if action == "reject":
        await state.update_data(order_id=order_id)
        await callback.message.answer(
            "Укажите причину отклонения заявки.\n"
            "Если комментарий не нужен, отправьте «-»."
        )
        await state.set_state(ManagerState.waiting_for_reject_reason)
        await callback.answer()
        return

    if action == "edit":
        await state.update_data(order_id=order_id)
        await callback.message.answer(
            "Отправьте новые данные заявки тремя строками:\n"
            "1. Время\n"
            "2. Адрес\n"
            "3. Телефон\n\n"
            "Чтобы оставить поле без изменений, отправьте «-» в нужной строке.\n\n"
            f"Текущие данные:\n{format_order(order)}"
        )
        await state.set_state(ManagerState.waiting_for_edit_data)
        await callback.answer()
        return

    await callback.answer("Неизвестное действие", show_alert=True)


@router.message(ManagerState.waiting_for_reject_reason)
async def process_reject_reason(message: types.Message, state: FSMContext):
    if not is_manager(message.from_user.id):
        await message.answer("❌ Доступ запрещен")
        await state.clear()
        return

    data = await state.get_data()
    _, order = find_order(data.get("order_id"))
    if not is_manager_order_available(order):
        await message.answer("Заявка уже обработана")
        await state.clear()
        return

    reason = message.text.strip()
    if reason == "-":
        reason = ""
    manager_name = get_manager_name(message.from_user.id) or "Руководитель"
    order_id = data.get("order_id")

    if api_client.is_configured():
        try:
            result = await api_client.review_request(
                telegram_id=message.from_user.id,
                temp_request_id=int(order_id),
                action="reject",
                rejection_reason=reason,
            )
            notification = f"❌ Ваша заявка на такси отклонена.\n\n{_format_api_request(result)}"
            if reason:
                notification += f"\n\nПричина: {reason}"
            owner_tg = order.get("telegram_id") or get_employee_telegram_id(order.get("employee_id"))
            if owner_tg:
                try:
                    await bot.send_message(int(owner_tg), notification)
                except Exception as e:
                    logger.error("Ошибка уведомления: %s", e)
            await message.answer(f"❌ Заявка отклонена\n\n{_format_api_request(result)}")
            await state.clear()
            return
        except api_client.ApiError as e:
            logger.warning("review_request reject failed: %s", e)

    set_order_status(order, "rejected", message.from_user.id, manager_name, reason)

    notification = f"❌ Ваша заявка на такси отклонена.\n\n{format_order(order, include_manager_info=False)}"
    if reason:
        notification += f"\n\nПричина: {reason}"
    await notify_order_owner(order, notification)

    await message.answer(f"❌ Заявка отклонена\n\n{format_order(order)}")
    await state.clear()


@router.message(ManagerState.waiting_for_edit_data)
async def process_edit_data(message: types.Message, state: FSMContext):
    if not is_manager(message.from_user.id):
        await message.answer("❌ Доступ запрещен")
        await state.clear()
        return

    data = await state.get_data()
    _, order = find_order(data.get("order_id"))
    if not is_manager_order_available(order):
        await message.answer("Заявка уже обработана")
        await state.clear()
        return

    parts = [part.strip() for part in message.text.splitlines() if part.strip()]
    if len(parts) < 3:
        parts = [part.strip() for part in message.text.split(";") if part.strip()]
    if len(parts) < 3:
        await message.answer(
            "❌ Нужно отправить 3 строки: время, адрес, телефон.\n"
            "Например:\n23:30\nул. Ленина, д. 12\n89131234567"
        )
        return

    time_str = order.get("time", "") if parts[0] == "-" else parts[0]
    address = order.get("address", "") if parts[1] == "-" else parts[1]
    raw_phone = order.get("phone", "") if parts[2] == "-" else parts[2]

    try:
        order_time = datetime.strptime(time_str, "%H:%M").time()
    except ValueError:
        await message.answer("❌ Неверный формат времени. Введите время в формате ЧЧ:ММ")
        return

    if is_forbidden_order_time(order_time):
        await message.answer("❌ Такси нельзя заказать на время с 06:01 до 21:59")
        return

    is_valid, checked_address = validate_address(address)
    if not is_valid:
        await message.answer(f"❌ {checked_address}")
        return

    normalized_phone = normalize_phone(raw_phone)
    if not re.match(r"^\+7\d{10}$", normalized_phone):
        await message.answer("❌ Неверный формат номера. Введите номер в формате 89131234567 или +79123456789")
        return

    manager_name = get_manager_name(message.from_user.id) or "Руководитель"
    edit_order(order, checked_address, normalized_phone, time_str, message.from_user.id, manager_name)

    await notify_order_owner(
        order,
        "✏️ Ваша заявка на такси изменена руководителем.\n\n"
        f"{format_order(order, include_manager_info=False)}"
    )
    await message.answer(f"✏️ Заявка изменена\n\n{format_order(order)}")
    await state.clear()
