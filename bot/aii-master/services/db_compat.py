from copy import deepcopy
from datetime import datetime, time as dt_time, timedelta

from config import DATABASE_FILE
from storage import contacts_db, database_db, employees_db, managers_db, orders_db, roles_db, save_data, users_db


ROLE_EMPLOYEE = 1
ROLE_MANAGER = 2
ROLE_ADMIN = 3

NOTIFICATION_UNREAD = 0

TEMP_STATUS_PENDING = 1
TEMP_STATUS_APPROVED = 2
TEMP_STATUS_REJECTED = 3
TEMP_STATUS_CANCELLED = 5

REQUEST_STATUS_APPROVED = 2

TABLES = (
    "users",
    "user_addresses",
    "temp_requests",
    "requests",
    "notifications",
    "audit_logs",
    "invitations",
)


def now_sql():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def sql_datetime(value):
    if isinstance(value, datetime):
        return value.strftime("%Y-%m-%d %H:%M:%S")
    return value


def resolve_order_datetime(time_str, created_at=None):
    created_at = created_at or datetime.now()
    order_time = datetime.strptime(time_str, "%H:%M").time()
    order_datetime = datetime.combine(created_at.date(), order_time)

    if order_time <= dt_time(6, 0) and created_at.time() > dt_time(6, 0):
        order_datetime += timedelta(days=1)

    return order_datetime.strftime("%Y-%m-%d %H:%M:%S")


def parse_sql_datetime(value):
    if not value:
        return None
    try:
        return datetime.strptime(str(value), "%Y-%m-%d %H:%M:%S")
    except ValueError:
        try:
            return datetime.strptime(str(value), "%Y-%m-%d")
        except ValueError:
            return None


def ensure_database_tables():
    changed = False
    for table in TABLES:
        if table not in database_db or not isinstance(database_db[table], list):
            database_db[table] = []
            changed = True
    return changed


def save_database():
    ensure_database_tables()
    save_data(DATABASE_FILE, database_db)


def next_id(table):
    ensure_database_tables()
    ids = [
        row.get("id", 0)
        for row in database_db[table]
        if isinstance(row, dict) and isinstance(row.get("id"), int)
    ]
    return max(ids, default=0) + 1


def find_row(table, column, value):
    ensure_database_tables()
    for row in database_db[table]:
        if row.get(column) == value:
            return row
    return None


def find_user_by_employee_id(employee_id):
    return find_row("users", "employee_number", str(employee_id))


def find_user_by_telegram_id(telegram_id):
    if telegram_id is None:
        return None
    return find_row("users", "telegram_id", str(telegram_id))


def get_role_for_employee_id(employee_id, default=ROLE_EMPLOYEE):
    role = roles_db.get(str(employee_id))
    if role == "admin":
        return ROLE_ADMIN
    if role == "manager":
        return ROLE_MANAGER
    return default


def get_role_for_telegram_id(telegram_id, default=ROLE_EMPLOYEE):
    employee_id = users_db.get(str(telegram_id))
    if employee_id:
        return get_role_for_employee_id(employee_id, default)
    if str(telegram_id) in managers_db:
        return ROLE_MANAGER
    return default


def sync_user(employee_id, full_name, telegram_id=None, phone=None, role=ROLE_EMPLOYEE):
    ensure_database_tables()
    employee_id = str(employee_id)
    role = get_role_for_employee_id(employee_id, role)
    telegram_id = str(telegram_id) if telegram_id is not None else None
    current_time = now_sql()

    row = find_user_by_employee_id(employee_id)
    if row is None and telegram_id:
        row = find_user_by_telegram_id(telegram_id)

    if row is None:
        row = {
            "id": next_id("users"),
            "full_name": full_name,
            "employee_number": employee_id,
            "phone": phone,
            "password": "telegram_auth",
            "role": role,
            "is_active": 1,
            "telegram_id": telegram_id,
            "do_not_disturb_until": None,
            "remember_token": None,
            "created_at": current_time,
            "updated_at": current_time,
            "deleted_at": None,
        }
        database_db["users"].append(row)
        return row

    row["full_name"] = full_name or row.get("full_name")
    row["employee_number"] = employee_id
    if phone:
        row["phone"] = phone
    if telegram_id:
        row["telegram_id"] = telegram_id
    row["role"] = role
    row["is_active"] = int(row.get("is_active", 1))
    row["updated_at"] = current_time
    return row


def sync_telegram_user(telegram_id, full_name=None, role=ROLE_EMPLOYEE):
    telegram_id = str(telegram_id)
    employee_id = users_db.get(telegram_id)
    if employee_id:
        return sync_user(
            employee_id,
            full_name or employees_db.get(employee_id, "Telegram user"),
            telegram_id=telegram_id,
            phone=contacts_db.get(employee_id, {}).get("phone"),
            role=role,
        )

    row = find_user_by_telegram_id(telegram_id)
    current_time = now_sql()
    if row is None:
        row = {
            "id": next_id("users"),
            "full_name": full_name or "Telegram user",
            "employee_number": f"TG{telegram_id}",
            "phone": None,
            "password": "telegram_auth",
            "role": role,
            "is_active": 1,
            "telegram_id": telegram_id,
            "do_not_disturb_until": None,
            "remember_token": None,
            "created_at": current_time,
            "updated_at": current_time,
            "deleted_at": None,
        }
        database_db["users"].append(row)
        return row

    if full_name:
        row["full_name"] = full_name
    row["role"] = max(int(row.get("role") or role), role)
    row["updated_at"] = current_time
    return row


def sync_user_address(employee_id, address):
    if not address:
        return None

    user = find_user_by_employee_id(employee_id)
    if user is None:
        full_name = employees_db.get(str(employee_id), "Telegram user")
        user = sync_user(employee_id, full_name)

    current_time = now_sql()
    for row in database_db["user_addresses"]:
        if row.get("user_id") == user["id"] and row.get("address") == address:
            row["updated_at"] = current_time
            return row

    row = {
        "id": next_id("user_addresses"),
        "user_id": user["id"],
        "address": address,
        "created_at": current_time,
        "updated_at": current_time,
    }
    database_db["user_addresses"].append(row)
    return row


def add_audit_log(user_id, action, entity_type, entity_id=None, old_values=None, new_values=None):
    row = {
        "id": next_id("audit_logs"),
        "user_id": user_id,
        "action": action,
        "entity_type": entity_type,
        "entity_id": entity_id,
        "old_values": deepcopy(old_values) if old_values is not None else None,
        "new_values": deepcopy(new_values) if new_values is not None else None,
        "ip_address": "telegram_bot",
        "user_agent": "Telegram Bot",
        "created_at": now_sql(),
    }
    database_db["audit_logs"].append(row)
    return row


def add_notification(user_id, title, message):
    current_time = now_sql()
    row = {
        "id": next_id("notifications"),
        "user_id": user_id,
        "title": title,
        "message": message,
        "status": NOTIFICATION_UNREAD,
        "created_at": current_time,
        "updated_at": current_time,
    }
    database_db["notifications"].append(row)
    return row


def create_temp_request(employee_id, full_name, telegram_id, address, phone, time_str):
    role = get_role_for_telegram_id(telegram_id)
    user = sync_user(employee_id, full_name, telegram_id=telegram_id, phone=phone, role=role)
    sync_user_address(employee_id, address)

    current_time = now_sql()
    row = {
        "id": next_id("temp_requests"),
        "user_id": user["id"],
        "full_name": full_name,
        "phone": phone,
        "date_time": resolve_order_datetime(time_str),
        "address_raw": address,
        "address_norm": None,
        "lat": None,
        "lon": None,
        "status": TEMP_STATUS_PENDING,
        "reviewed_by": None,
        "reviewed_at": None,
        "manager_comment": None,
        "rejection_reason": None,
        "cancelled_at": None,
        "created_at": current_time,
        "updated_at": current_time,
        "deleted_at": None,
    }
    database_db["temp_requests"].append(row)
    add_audit_log(
        user["id"],
        "temp_request_created",
        "TempRequest",
        row["id"],
        new_values={
            "phone": phone,
            "date_time": row["date_time"],
            "full_name": full_name,
            "address_raw": address,
        },
    )
    save_database()
    return row


def ensure_temp_request_for_order(order, employee_id):
    temp_request_id = order.get("temp_request_id")
    try:
        temp_request_id = int(temp_request_id)
    except (TypeError, ValueError):
        temp_request_id = None

    if temp_request_id is not None:
        existing = find_row("temp_requests", "id", temp_request_id)
        if existing is not None:
            return existing

    employee_id = str(employee_id)
    full_name = order.get("employee_name") or order.get("full_name") or employees_db.get(employee_id, "Telegram user")
    telegram_id = order.get("telegram_id")
    if not telegram_id:
        for tg_id, linked_employee_id in users_db.items():
            if linked_employee_id == employee_id:
                telegram_id = tg_id
                break

    phone = order.get("phone")
    address = order.get("address") or order.get("address_raw")
    time_str = order.get("time") or "00:00"
    created_at = parse_sql_datetime(order.get("timestamp")) or parse_sql_datetime(order.get("created_at")) or datetime.now()
    user = sync_user(employee_id, full_name, telegram_id=telegram_id, phone=phone)
    if address:
        sync_user_address(employee_id, address)

    status = order.get("status", "approved")
    status_code = {
        "pending": TEMP_STATUS_PENDING,
        "approved": TEMP_STATUS_APPROVED,
        "edited": TEMP_STATUS_APPROVED,
        "rejected": TEMP_STATUS_REJECTED,
    }.get(status, TEMP_STATUS_PENDING)

    reviewed_at = order.get("decision_timestamp")
    if status_code in (TEMP_STATUS_APPROVED, TEMP_STATUS_REJECTED) and not reviewed_at:
        reviewed_at = order.get("updated_at") or order.get("timestamp") or now_sql()

    row = {
        "id": next_id("temp_requests"),
        "user_id": user["id"],
        "full_name": full_name,
        "phone": phone or "",
        "date_time": order.get("date_time") or resolve_order_datetime(time_str, created_at),
        "address_raw": address or "",
        "address_norm": order.get("address_norm"),
        "lat": order.get("lat"),
        "lon": order.get("lon"),
        "status": status_code,
        "reviewed_by": None,
        "reviewed_at": reviewed_at if status_code != TEMP_STATUS_PENDING else None,
        "manager_comment": order.get("manager_comment") or None,
        "rejection_reason": order.get("manager_comment") if status_code == TEMP_STATUS_REJECTED else None,
        "cancelled_at": None,
        "created_at": order.get("created_at") or order.get("timestamp") or sql_datetime(created_at),
        "updated_at": order.get("updated_at") or order.get("decision_timestamp") or order.get("timestamp") or now_sql(),
        "deleted_at": None,
    }
    database_db["temp_requests"].append(row)

    order["temp_request_id"] = row["id"]
    order["user_id"] = row["user_id"]
    order["full_name"] = row["full_name"]
    order["date_time"] = row["date_time"]
    order["address_raw"] = row["address_raw"]
    order["address_norm"] = row["address_norm"]
    order["lat"] = row["lat"]
    order["lon"] = row["lon"]
    order["status_code"] = row["status"]
    order["created_at"] = row["created_at"]
    order["updated_at"] = row["updated_at"]

    if not order.get("id") or not str(order.get("id")).isdigit():
        order["id"] = str(row["id"])

    add_audit_log(
        row["user_id"],
        "temp_request_created",
        "TempRequest",
        row["id"],
        new_values={
            "phone": row["phone"],
            "date_time": row["date_time"],
            "full_name": row["full_name"],
            "address_raw": row["address_raw"],
        },
    )

    if status_code == TEMP_STATUS_APPROVED:
        request_row = create_or_update_final_request(row, None)
        order["request_id"] = request_row["id"]

    save_database()
    return row


def update_temp_request_from_order(order):
    temp_request_id = order.get("temp_request_id") or order.get("id")
    try:
        temp_request_id = int(temp_request_id)
    except (TypeError, ValueError):
        return None

    row = find_row("temp_requests", "id", temp_request_id)
    if row is None:
        return None

    row["full_name"] = order.get("employee_name") or order.get("full_name") or row["full_name"]
    row["phone"] = order.get("phone") or row["phone"]
    if order.get("time"):
        created_at = parse_sql_datetime(row.get("created_at")) or datetime.now()
        row["date_time"] = resolve_order_datetime(order["time"], created_at)
    elif order.get("date_time"):
        row["date_time"] = order["date_time"]
    row["address_raw"] = order.get("address") or order.get("address_raw") or row["address_raw"]
    row["updated_at"] = now_sql()
    order["date_time"] = row["date_time"]
    order["address_raw"] = row["address_raw"]
    order["updated_at"] = row["updated_at"]
    return row


def create_or_update_final_request(temp_row, manager_user_id):
    request_row = find_row("requests", "temp_request_id", temp_row["id"])
    current_time = now_sql()

    values = {
        "user_id": temp_row["user_id"],
        "temp_request_id": temp_row["id"],
        "approved_at": temp_row["reviewed_at"] or current_time,
        "full_name": temp_row["full_name"],
        "phone": temp_row["phone"],
        "date_time": temp_row["date_time"],
        "address_raw": temp_row["address_raw"],
        "address_norm": temp_row.get("address_norm"),
        "lat": temp_row.get("lat"),
        "lon": temp_row.get("lon"),
        "status": REQUEST_STATUS_APPROVED,
        "approved_by": manager_user_id,
        "manager_comment": temp_row.get("manager_comment"),
        "rejection_reason": None,
        "cancelled_at": None,
        "updated_at": current_time,
        "deleted_at": None,
        "exported_at": None,
    }

    if request_row is None:
        request_row = {
            "id": next_id("requests"),
            **values,
            "created_at": current_time,
        }
        database_db["requests"].append(request_row)
        add_audit_log(
            manager_user_id,
            "final_request_created",
            "Request",
            request_row["id"],
            new_values={
                "phone": request_row["phone"],
                "date_time": request_row["date_time"],
                "full_name": request_row["full_name"],
                "address_raw": request_row["address_raw"],
                "temp_request_id": temp_row["id"],
            },
        )
    else:
        old_values = deepcopy(request_row)
        request_row.update(values)
        add_audit_log(
            manager_user_id,
            "final_request_updated",
            "Request",
            request_row["id"],
            old_values=old_values,
            new_values=request_row,
        )

    return request_row


def review_order(order, status, manager_telegram_id, manager_name, comment=None):
    manager_role = get_role_for_telegram_id(manager_telegram_id, ROLE_MANAGER)
    manager_user = sync_telegram_user(manager_telegram_id, manager_name, role=manager_role)
    temp_row = update_temp_request_from_order(order)
    if temp_row is None:
        return None

    old_values = {
        "status": temp_row.get("status"),
        "manager_comment": temp_row.get("manager_comment"),
        "rejection_reason": temp_row.get("rejection_reason"),
    }
    current_time = now_sql()
    temp_row["reviewed_by"] = manager_user["id"]
    temp_row["reviewed_at"] = current_time
    temp_row["updated_at"] = current_time

    if status == "rejected":
        temp_row["status"] = TEMP_STATUS_REJECTED
        temp_row["manager_comment"] = None
        temp_row["rejection_reason"] = comment or None
        add_audit_log(
            manager_user["id"],
            "temp_request_reviewed",
            "TempRequest",
            temp_row["id"],
            old_values=old_values,
            new_values={
                "status": TEMP_STATUS_REJECTED,
                "manager_comment": None,
                "rejection_reason": temp_row["rejection_reason"],
            },
        )
        add_notification(
            temp_row["user_id"],
            "Taxi request rejected",
            comment or "Your taxi request was rejected.",
        )
        save_database()
        return temp_row

    temp_row["status"] = TEMP_STATUS_APPROVED
    temp_row["manager_comment"] = comment or temp_row.get("manager_comment")
    temp_row["rejection_reason"] = None
    add_audit_log(
        manager_user["id"],
        "temp_request_reviewed",
        "TempRequest",
        temp_row["id"],
        old_values=old_values,
        new_values={
            "status": TEMP_STATUS_APPROVED,
            "manager_comment": temp_row.get("manager_comment"),
        },
    )

    request_row = create_or_update_final_request(temp_row, manager_user["id"])
    order["request_id"] = request_row["id"]
    add_audit_log(
        manager_user["id"],
        "temp_request_finalized",
        "TempRequest",
        temp_row["id"],
        old_values={"status": TEMP_STATUS_APPROVED},
        new_values={"final_request_id": request_row["id"]},
    )
    add_notification(
        temp_row["user_id"],
        "Taxi request approved",
        "Your taxi request was approved.",
    )
    save_database()
    return request_row


def soft_delete_order(order):
    current_time = now_sql()

    temp_request_id = order.get("temp_request_id") or order.get("id")
    try:
        temp_request_id = int(temp_request_id)
    except (TypeError, ValueError):
        temp_request_id = None

    if temp_request_id is not None:
        temp_row = find_row("temp_requests", "id", temp_request_id)
        if temp_row and not temp_row.get("deleted_at"):
            temp_row["status"] = TEMP_STATUS_CANCELLED
            temp_row["cancelled_at"] = current_time
            temp_row["deleted_at"] = current_time
            temp_row["updated_at"] = current_time
            add_audit_log(
                temp_row.get("user_id"),
                "temp_request_deleted",
                "TempRequest",
                temp_row["id"],
                old_values={
                    "phone": temp_row.get("phone"),
                    "status": temp_row.get("status"),
                    "date_time": temp_row.get("date_time"),
                    "full_name": temp_row.get("full_name"),
                    "address_raw": temp_row.get("address_raw"),
                },
            )

    request_id = order.get("request_id")
    if request_id is not None:
        request_row = find_row("requests", "id", request_id)
        if request_row and not request_row.get("deleted_at"):
            request_row["cancelled_at"] = current_time
            request_row["deleted_at"] = current_time
            request_row["updated_at"] = current_time


def ensure_database_schema_from_json():
    changed = ensure_database_tables()

    for employee_id, full_name in employees_db.items():
        contact = contacts_db.get(employee_id, {})
        telegram_id = None
        for tg_id, linked_employee_id in users_db.items():
            if linked_employee_id == employee_id:
                telegram_id = tg_id
                break
        before_count = len(database_db["users"])
        sync_user(
            employee_id,
            full_name,
            telegram_id=telegram_id,
            phone=contact.get("phone"),
            role=get_role_for_employee_id(employee_id),
        )
        if contact.get("address"):
            sync_user_address(employee_id, contact["address"])
        changed = changed or len(database_db["users"]) != before_count

    for telegram_id, manager_data in managers_db.items():
        before_count = len(database_db["users"])
        sync_telegram_user(telegram_id, manager_data.get("full_name"), role=ROLE_MANAGER)
        changed = changed or len(database_db["users"]) != before_count

    for employee_id, employee_orders in orders_db.items():
        for order in employee_orders:
            if isinstance(order, dict):
                before_count = len(database_db["temp_requests"])
                ensure_temp_request_for_order(order, employee_id)
                changed = changed or len(database_db["temp_requests"]) != before_count

    save_database()
