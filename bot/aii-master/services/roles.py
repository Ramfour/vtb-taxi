from config import ADMIN_EMPLOYEE_IDS, ADMIN_IDS, MANAGERS_FILE, ROLES_FILE
from storage import employees_db, managers_db, roles_db, save_data, users_db


ROLE_EMPLOYEE = "employee"
ROLE_MANAGER = "manager"
ROLE_ADMIN = "admin"

ROLE_TEXT = {
    ROLE_EMPLOYEE: "Сотрудник",
    ROLE_MANAGER: "Руководитель",
    ROLE_ADMIN: "Администратор",
}

ROLE_ALIASES = {
    "employee": ROLE_EMPLOYEE,
    "user": ROLE_EMPLOYEE,
    "сотрудник": ROLE_EMPLOYEE,
    "1": ROLE_EMPLOYEE,
    "manager": ROLE_MANAGER,
    "руководитель": ROLE_MANAGER,
    "lead": ROLE_MANAGER,
    "2": ROLE_MANAGER,
    "admin": ROLE_ADMIN,
    "administrator": ROLE_ADMIN,
    "админ": ROLE_ADMIN,
    "администратор": ROLE_ADMIN,
    "3": ROLE_ADMIN,
}


def normalize_role(role):
    return ROLE_ALIASES.get(str(role).strip().lower())


def save_roles():
    save_data(ROLES_FILE, roles_db)


def get_employee_id_by_telegram_id(telegram_id):
    return users_db.get(str(telegram_id))


def get_employee_role(employee_id):
    return normalize_role(roles_db.get(str(employee_id))) or ROLE_EMPLOYEE


def get_telegram_role(telegram_id):
    employee_id = get_employee_id_by_telegram_id(telegram_id)
    if employee_id:
        return get_employee_role(employee_id)
    if str(telegram_id) in ADMIN_IDS:
        return ROLE_ADMIN
    return ROLE_EMPLOYEE


def get_role_text(role):
    return ROLE_TEXT.get(normalize_role(role), "Сотрудник")


def set_employee_role(employee_id, role):
    employee_id = str(employee_id)
    normalized_role = normalize_role(role)
    if not normalized_role:
        raise ValueError("Некорректная роль")
    if employee_id not in employees_db:
        raise KeyError("Сотрудник не найден")

    roles_db[employee_id] = normalized_role
    save_roles()
    return normalized_role


def is_admin(telegram_id):
    return get_telegram_role(telegram_id) == ROLE_ADMIN


def is_manager(telegram_id):
    return get_telegram_role(telegram_id) in (ROLE_MANAGER, ROLE_ADMIN)


def get_manager_name(telegram_id):
    employee_id = get_employee_id_by_telegram_id(telegram_id)
    if employee_id:
        return employees_db.get(employee_id, ROLE_TEXT[get_employee_role(employee_id)])
    if str(telegram_id) in ADMIN_IDS:
        return "Администратор"
    return None


def add_manager(employee_id):
    role = set_employee_role(employee_id, ROLE_MANAGER)
    return {
        "employee_id": str(employee_id),
        "full_name": employees_db[str(employee_id)],
        "role": role,
    }


def remove_manager(employee_id):
    employee_id = str(employee_id)
    if employee_id not in employees_db:
        return False
    if get_employee_role(employee_id) != ROLE_MANAGER:
        return False
    roles_db[employee_id] = ROLE_EMPLOYEE
    save_roles()
    return True


def delete_employee_role(employee_id):
    employee_id = str(employee_id)
    if employee_id in roles_db:
        del roles_db[employee_id]
        save_roles()


def list_employees_by_role(*roles):
    normalized_roles = {normalize_role(role) for role in roles}
    normalized_roles.discard(None)
    return [
        (employee_id, employees_db[employee_id])
        for employee_id in employees_db
        if get_employee_role(employee_id) in normalized_roles
    ]


def get_telegram_ids_by_role(*roles):
    normalized_roles = {normalize_role(role) for role in roles}
    normalized_roles.discard(None)
    result = []
    for telegram_id, employee_id in users_db.items():
        if get_employee_role(employee_id) in normalized_roles and str(telegram_id).isdigit():
            result.append(str(telegram_id))
    return result


def get_manager_telegram_ids():
    return get_telegram_ids_by_role(ROLE_MANAGER, ROLE_ADMIN)


def get_admin_telegram_ids():
    result = set(get_telegram_ids_by_role(ROLE_ADMIN))
    for admin_id in ADMIN_IDS:
        if str(admin_id).isdigit():
            result.add(str(admin_id))
    return sorted(result)


def ensure_roles_schema():
    changed = False

    for employee_id in employees_db:
        if normalize_role(roles_db.get(employee_id)) is None:
            roles_db[employee_id] = ROLE_EMPLOYEE
            changed = True

    for telegram_id, manager_data in managers_db.items():
        employee_id = manager_data.get("employee_id") or users_db.get(str(telegram_id))
        if employee_id in employees_db and get_employee_role(employee_id) == ROLE_EMPLOYEE:
            roles_db[employee_id] = ROLE_MANAGER
            changed = True

    for admin_telegram_id in ADMIN_IDS:
        employee_id = users_db.get(str(admin_telegram_id))
        if employee_id in employees_db and get_employee_role(employee_id) != ROLE_ADMIN:
            roles_db[employee_id] = ROLE_ADMIN
            changed = True

    for employee_id in ADMIN_EMPLOYEE_IDS:
        if employee_id in employees_db and get_employee_role(employee_id) != ROLE_ADMIN:
            roles_db[employee_id] = ROLE_ADMIN
            changed = True

    if changed:
        save_roles()

    if managers_db:
        save_data(MANAGERS_FILE, managers_db)
