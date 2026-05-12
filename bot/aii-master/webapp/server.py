import hashlib
import hmac
import json
import logging
import os
import re
import time
from datetime import datetime
from urllib.parse import parse_qsl

from aiohttp import web

from config import (
    BOT_TOKEN,
    CONTACTS_FILE,
    EMPLOYEES_FILE,
    ORDERS_FILE,
    REMINDERS_FILE,
    USERS_FILE,
    WEBAPP_HOST,
    WEBAPP_PORT,
    WEBAPP_DEV_TELEGRAM_ID,
)
from handlers.reminders import add_or_update_reminder_job, get_reminder_schedule_text
from services.db_compat import save_database, sync_user, sync_user_address
from services.geocoder import validate_address
from services.order_service import create_order, get_status_text
from storage import contacts_db, employees_db, orders_db, reminders_db, save_data, users_db
from validators import is_forbidden_order_time, normalize_phone


logger = logging.getLogger(__name__)
WEBAPP_DIR = os.path.dirname(os.path.abspath(__file__))
STATIC_DIR = os.path.join(WEBAPP_DIR, "static")
INIT_DATA_MAX_AGE = 24 * 60 * 60
VALID_REMINDER_DAYS = ["mon", "tue", "wed", "thu", "fri", "sat", "sun"]
VALID_REMINDER_SCHEDULES = ["daily", "weekdays", "custom"]


def json_response(data, status=200):
    return web.json_response(data, status=status, dumps=lambda value: json.dumps(value, ensure_ascii=False))


def validate_init_data(init_data):
    if not init_data:
        raise web.HTTPUnauthorized(text="Telegram initData отсутствует")

    parsed_items = parse_qsl(init_data, keep_blank_values=True)
    parsed_data = dict(parsed_items)
    received_hash = parsed_data.pop("hash", None)
    if not received_hash:
        raise web.HTTPUnauthorized(text="Подпись initData отсутствует")

    data_check_string = "\n".join(
        f"{key}={value}"
        for key, value in sorted(parsed_data.items())
    )
    secret_key = hmac.new(b"WebAppData", BOT_TOKEN.encode("utf-8"), hashlib.sha256).digest()
    calculated_hash = hmac.new(
        secret_key,
        data_check_string.encode("utf-8"),
        hashlib.sha256
    ).hexdigest()

    if not hmac.compare_digest(calculated_hash, received_hash):
        raise web.HTTPUnauthorized(text="Некорректная подпись initData")

    auth_date = int(parsed_data.get("auth_date", "0") or 0)
    if auth_date and time.time() - auth_date > INIT_DATA_MAX_AGE:
        raise web.HTTPUnauthorized(text="Срок действия initData истёк")

    try:
        user = json.loads(parsed_data.get("user", "{}"))
    except json.JSONDecodeError as e:
        raise web.HTTPUnauthorized(text="Некорректные данные пользователя") from e

    if "id" not in user:
        raise web.HTTPUnauthorized(text="Telegram ID пользователя отсутствует")

    return user


def get_request_user(request):
    init_data = request.headers.get("X-Telegram-Init-Data", "")
    if not init_data and WEBAPP_DEV_TELEGRAM_ID and request.remote in ("127.0.0.1", "::1"):
        return {
            "id": int(WEBAPP_DEV_TELEGRAM_ID),
            "first_name": "Dev",
        }
    return validate_init_data(init_data)


async def read_json(request):
    try:
        return await request.json()
    except json.JSONDecodeError:
        raise web.HTTPBadRequest(text="Некорректный JSON")


def get_authorized_employee(telegram_id):
    employee_id = users_db.get(str(telegram_id))
    if not employee_id:
        return None
    full_name = employees_db.get(employee_id)
    if not full_name:
        return None
    return employee_id, full_name


def serialize_orders(employee_id):
    return [
        {
            **order,
            "status_text": get_status_text(order.get("status", "approved")),
        }
        for order in orders_db.get(employee_id, [])
    ]


def serialize_reminder(employee_id):
    reminder_data = reminders_db.get(employee_id)
    if not reminder_data:
        return None
    return {
        **reminder_data,
        "schedule_text": get_reminder_schedule_text(reminder_data),
    }


async def index(_request):
    return web.FileResponse(os.path.join(STATIC_DIR, "index.html"))


async def api_me(request):
    user = get_request_user(request)
    employee = get_authorized_employee(user["id"])
    if not employee:
        return json_response({
            "authorized": False,
            "telegram_user": {
                "id": user["id"],
                "first_name": user.get("first_name", ""),
                "last_name": user.get("last_name", ""),
                "username": user.get("username", ""),
            }
        })

    employee_id, full_name = employee
    return json_response({
        "authorized": True,
        "telegram_user": {
            "id": user["id"],
            "first_name": user.get("first_name", ""),
            "last_name": user.get("last_name", ""),
            "username": user.get("username", ""),
        },
        "employee": {
            "id": employee_id,
            "full_name": full_name,
        },
        "contact": contacts_db.get(employee_id),
        "reminder": serialize_reminder(employee_id),
        "orders": serialize_orders(employee_id),
    })


async def api_register(request):
    user = get_request_user(request)
    payload = await read_json(request)
    employee_id = str(payload.get("employee_id", "")).strip()
    address = str(payload.get("address", "")).strip()
    raw_phone = str(payload.get("phone", "")).strip()
    telegram_id = str(user["id"])

    if employee_id not in employees_db:
        return json_response({"ok": False, "error": "Табельный номер не найден"}, status=404)

    for linked_tg_id, linked_employee_id in users_db.items():
        if linked_employee_id == employee_id and linked_tg_id != telegram_id:
            return json_response({
                "ok": False,
                "error": "Этот табельный номер уже привязан к другому аккаунту Telegram"
            }, status=409)

    is_valid, checked_address = validate_address(address)
    if not is_valid:
        return json_response({"ok": False, "error": checked_address}, status=400)

    normalized_phone = normalize_phone(raw_phone)
    if not re.match(r"^\+7\d{10}$", normalized_phone):
        return json_response({"ok": False, "error": "Введите российский номер телефона"}, status=400)

    users_db[telegram_id] = employee_id
    contacts_db[employee_id] = {
        "address": checked_address,
        "phone": normalized_phone,
    }
    sync_user(employee_id, employees_db[employee_id], telegram_id=telegram_id, phone=normalized_phone)
    sync_user_address(employee_id, checked_address)
    save_database()
    if employee_id not in orders_db:
        orders_db[employee_id] = []

    save_data(USERS_FILE, users_db)
    save_data(CONTACTS_FILE, contacts_db)
    save_data(ORDERS_FILE, orders_db)

    return json_response({
        "ok": True,
        "employee": {
            "id": employee_id,
            "full_name": employees_db[employee_id],
        },
        "contact": contacts_db[employee_id],
    })


async def api_create_order(request):
    user = get_request_user(request)
    employee = get_authorized_employee(user["id"])
    if not employee:
        return json_response({"ok": False, "error": "Сначала пройдите авторизацию"}, status=401)

    employee_id, full_name = employee
    payload = await read_json(request)
    time_str = str(payload.get("time", "")).strip()
    address = str(payload.get("address", "")).strip()
    raw_phone = str(payload.get("phone", "")).strip()

    try:
        order_time = datetime.strptime(time_str, "%H:%M").time()
    except ValueError:
        return json_response({"ok": False, "error": "Введите время в формате ЧЧ:ММ"}, status=400)

    if is_forbidden_order_time(order_time):
        return json_response({
            "ok": False,
            "error": "Такси нельзя заказать на время с 06:01 до 21:59"
        }, status=400)

    if not address or not raw_phone:
        saved_contact = contacts_db.get(employee_id, {})
        address = address or saved_contact.get("address", "")
        raw_phone = raw_phone or saved_contact.get("phone", "")

    is_valid, checked_address = validate_address(address)
    if not is_valid:
        return json_response({"ok": False, "error": checked_address}, status=400)

    normalized_phone = normalize_phone(raw_phone)
    if not re.match(r"^\+7\d{10}$", normalized_phone):
        return json_response({"ok": False, "error": "Введите российский номер телефона"}, status=400)

    order = create_order(
        employee_id,
        full_name,
        user["id"],
        checked_address,
        normalized_phone,
        time_str
    )

    return json_response({
        "ok": True,
        "order": {
            **order,
            "status_text": get_status_text(order.get("status")),
        },
    })


async def api_orders(request):
    user = get_request_user(request)
    employee = get_authorized_employee(user["id"])
    if not employee:
        return json_response({"ok": False, "error": "Сначала пройдите авторизацию"}, status=401)
    employee_id, _ = employee
    return json_response({"ok": True, "orders": serialize_orders(employee_id)})


async def api_save_reminder(request):
    user = get_request_user(request)
    employee = get_authorized_employee(user["id"])
    if not employee:
        return json_response({"ok": False, "error": "Сначала пройдите авторизацию"}, status=401)

    employee_id, _ = employee
    payload = await read_json(request)
    time_str = str(payload.get("time", "")).strip()
    schedule_type = str(payload.get("schedule_type", "daily")).strip()
    days = payload.get("days", [])

    try:
        datetime.strptime(time_str, "%H:%M")
    except ValueError:
        return json_response({"ok": False, "error": "Введите время в формате ЧЧ:ММ"}, status=400)

    if schedule_type not in VALID_REMINDER_SCHEDULES:
        return json_response({"ok": False, "error": "Некорректный тип расписания"}, status=400)

    if schedule_type == "weekdays":
        days = ["mon", "tue", "wed", "thu", "fri"]
    elif schedule_type == "daily":
        days = []
    else:
        days = [day for day in days if day in VALID_REMINDER_DAYS]
        if not days:
            return json_response({"ok": False, "error": "Выберите хотя бы один день"}, status=400)

    reminders_db[employee_id] = {
        "time": time_str,
        "telegram_id": str(user["id"]),
        "active": True,
        "schedule_type": schedule_type,
        "days": days,
    }
    save_data(REMINDERS_FILE, reminders_db)
    add_or_update_reminder_job(employee_id)

    return json_response({
        "ok": True,
        "reminder": serialize_reminder(employee_id),
    })


def create_app():
    app = web.Application()
    app.router.add_get("/", index)
    app.router.add_static("/assets", STATIC_DIR)
    app.router.add_get("/api/me", api_me)
    app.router.add_post("/api/register", api_register)
    app.router.add_get("/api/orders", api_orders)
    app.router.add_post("/api/orders", api_create_order)
    app.router.add_post("/api/reminder", api_save_reminder)
    return app


async def start_webapp_server():
    app = create_app()
    runner = web.AppRunner(app)
    await runner.setup()
    site = web.TCPSite(runner, WEBAPP_HOST, WEBAPP_PORT)
    await site.start()
    logger.info("Mini App server started at http://%s:%s", WEBAPP_HOST, WEBAPP_PORT)
    return runner
