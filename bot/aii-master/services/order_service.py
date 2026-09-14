import uuid
from datetime import datetime

from config import ORDERS_FILE
from services.db_compat import create_temp_request, ensure_temp_request_for_order, review_order
from storage import employees_db, orders_db, save_data, users_db


ORDER_STATUS_TEXT = {
    "pending": "На согласовании",
    "approved": "Одобрена",
    "edited": "Изменена руководителем",
    "rejected": "Отклонена",
}


def get_status_text(status):
    return ORDER_STATUS_TEXT.get(status, status or "Неизвестно")


def build_order_text(employee_name, address, phone, time_str):
    return f"{employee_name}\n{address}\n{phone}\n{time_str}"


def get_employee_telegram_id(employee_id):
    for telegram_id, linked_employee_id in users_db.items():
        if linked_employee_id == employee_id:
            return telegram_id
    return None


def create_order(employee_id, employee_name, telegram_id, address, phone, time_str):
    now = datetime.now()
    temp_request = create_temp_request(employee_id, employee_name, telegram_id, address, phone, time_str)
    order = {
        "id": str(temp_request["id"]),
        "temp_request_id": temp_request["id"],
        "user_id": temp_request["user_id"],
        "employee_id": employee_id,
        "employee_name": employee_name,
        "full_name": temp_request["full_name"],
        "telegram_id": str(telegram_id),
        "address": address,
        "address_raw": temp_request["address_raw"],
        "address_norm": temp_request["address_norm"],
        "lat": temp_request["lat"],
        "lon": temp_request["lon"],
        "phone": phone,
        "time": time_str,
        "date_time": temp_request["date_time"],
        "text": build_order_text(employee_name, address, phone, time_str),
        "status": "pending",
        "status_code": temp_request["status"],
        "timestamp": now.strftime("%Y-%m-%d %H:%M:%S"),
        "date": now.strftime("%Y-%m-%d"),
        "created_at": temp_request["created_at"],
        "updated_at": temp_request["updated_at"],
    }
    orders_db.setdefault(employee_id, []).append(order)
    save_data(ORDERS_FILE, orders_db)
    return order


def ensure_order_schema(order, employee_id):
    changed = False
    if "id" not in order:
        order["id"] = uuid.uuid4().hex[:12]
        changed = True
    if "employee_id" not in order:
        order["employee_id"] = employee_id
        changed = True
    if "employee_name" not in order:
        order["employee_name"] = employees_db.get(employee_id, "Сотрудник")
        changed = True
    if "telegram_id" not in order:
        telegram_id = get_employee_telegram_id(employee_id)
        if telegram_id:
            order["telegram_id"] = telegram_id
            changed = True
    if "status" not in order:
        order["status"] = "approved"
        changed = True

    lines = str(order.get("text", "")).splitlines()
    if lines:
        if "address" not in order and len(lines) >= 2:
            order["address"] = lines[1]
            changed = True
        if "phone" not in order and len(lines) >= 3:
            order["phone"] = lines[2]
            changed = True
        if "time" not in order and len(lines) >= 4:
            order["time"] = lines[3]
            changed = True

    if "full_name" not in order:
        order["full_name"] = order.get("employee_name")
        changed = True
    if "address_raw" not in order and order.get("address"):
        order["address_raw"] = order.get("address")
        changed = True

    if "temp_request_id" not in order:
        ensure_temp_request_for_order(order, employee_id)
        changed = True

    return changed


def ensure_orders_schema():
    changed = False
    for employee_id, employee_orders in orders_db.items():
        for order in employee_orders:
            if ensure_order_schema(order, employee_id):
                changed = True
    if changed:
        save_data(ORDERS_FILE, orders_db)


def find_order(order_id):
    for employee_id, employee_orders in orders_db.items():
        for order in employee_orders:
            ensure_order_schema(order, employee_id)
            if str(order.get("id")) == str(order_id) or str(order.get("temp_request_id")) == str(order_id):
                return employee_id, order
    return None, None


def list_orders(status=None):
    result = []
    for employee_id, employee_orders in orders_db.items():
        for order in employee_orders:
            ensure_order_schema(order, employee_id)
            if status is None or order.get("status") == status:
                result.append(order)
    return sorted(result, key=lambda item: item.get("timestamp", ""), reverse=True)


def save_orders():
    save_data(ORDERS_FILE, orders_db)


def set_order_status(order, status, manager_id, manager_name, comment=None):
    order["status"] = status
    order["manager_id"] = str(manager_id)
    order["manager_name"] = manager_name
    order["manager_comment"] = comment or ""
    order["decision_timestamp"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    order["updated_at"] = order["decision_timestamp"]
    db_row = review_order(order, status, manager_id, manager_name, comment)
    if status == "rejected":
        order["status_code"] = 3
    else:
        order["status_code"] = 2
        if db_row and db_row.get("id"):
            order["request_id"] = db_row["id"]
    save_orders()


def edit_order(order, address, phone, time_str, manager_id, manager_name):
    order["address"] = address
    order["phone"] = phone
    order["time"] = time_str
    order["text"] = build_order_text(
        order.get("employee_name", "Сотрудник"),
        address,
        phone,
        time_str
    )
    set_order_status(order, "edited", manager_id, manager_name)


def format_order(order, include_manager_info=True):
    status = get_status_text(order.get("status"))
    text = (
        f"№ {order.get('id')}\n"
        f"Статус: {status}\n"
        f"Сотрудник: {order.get('employee_name', 'Сотрудник')}\n"
        f"Адрес: {order.get('address', '')}\n"
        f"Телефон: {order.get('phone', '')}\n"
        f"Время: {order.get('time', '')}"
    )
    if include_manager_info and order.get("manager_name"):
        text += f"\nРуководитель: {order['manager_name']}"
    if include_manager_info and order.get("manager_comment"):
        text += f"\nКомментарий: {order['manager_comment']}"
    return text
