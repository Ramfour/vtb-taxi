import json

from config import (
    CONTACTS_FILE,
    DATABASE_FILE,
    EMPLOYEES_FILE,
    MANAGERS_FILE,
    ORDERS_FILE,
    REMINDERS_FILE,
    ROLES_FILE,
    USERS_FILE,
)


def load_data(filename, default=None):
    try:
        with open(filename, "r", encoding="utf-8") as f:
            return json.load(f)
    except (FileNotFoundError, json.JSONDecodeError):
        return default if default is not None else {}


def save_data(filename, data):
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def default_database():
    return {
        "users": [],
        "user_addresses": [],
        "temp_requests": [],
        "requests": [],
        "notifications": [],
        "audit_logs": [],
        "invitations": [],
    }


def init_files():
    for file in [EMPLOYEES_FILE, USERS_FILE, ORDERS_FILE, REMINDERS_FILE, CONTACTS_FILE, MANAGERS_FILE, ROLES_FILE]:
        save_data(file, load_data(file, {}))
    save_data(DATABASE_FILE, load_data(DATABASE_FILE, default_database()))


employees_db = load_data(EMPLOYEES_FILE, {})
users_db = load_data(USERS_FILE, {})
orders_db = load_data(ORDERS_FILE, {})
reminders_db = load_data(REMINDERS_FILE, {})
contacts_db = load_data(CONTACTS_FILE, {})
managers_db = load_data(MANAGERS_FILE, {})
roles_db = load_data(ROLES_FILE, {})
database_db = load_data(DATABASE_FILE, default_database())


def get_employee_id_by_tg(tg_id):
    return users_db.get(str(tg_id))


def get_employee_data(employee_id):
    if employee_id in employees_db:
        return {"employee_id": employee_id, "full_name": employees_db[employee_id]}
    return None
