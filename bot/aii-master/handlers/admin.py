import json
import os
import shlex
from datetime import datetime

from aiogram import Router, types
from aiogram.filters import Command

from config import (
    BASE_DIR,
    CONTACTS_FILE,
    EMPLOYEES_FILE,
    ORDERS_FILE,
    REMINDERS_FILE,
    USERS_FILE,
)
from services.db_compat import (
    ROLE_ADMIN as DB_ROLE_ADMIN,
    ROLE_EMPLOYEE as DB_ROLE_EMPLOYEE,
    ROLE_MANAGER as DB_ROLE_MANAGER,
    save_database,
    sync_user,
)
from services.roles import (
    ROLE_ADMIN,
    ROLE_EMPLOYEE,
    ROLE_MANAGER,
    add_manager,
    delete_employee_role,
    get_employee_role,
    get_role_text,
    is_admin,
    list_employees_by_role,
    normalize_role,
    remove_manager,
    set_employee_role,
)
from storage import contacts_db, employees_db, orders_db, reminders_db, save_data, users_db


router = Router()


DB_ROLE_BY_ROLE = {
    ROLE_EMPLOYEE: DB_ROLE_EMPLOYEE,
    ROLE_MANAGER: DB_ROLE_MANAGER,
    ROLE_ADMIN: DB_ROLE_ADMIN,
}


def get_telegram_id_by_employee(employee_id):
    for telegram_id, linked_employee_id in users_db.items():
        if linked_employee_id == employee_id:
            return telegram_id
    return None


def sync_employee_role_to_database(employee_id):
    role = get_employee_role(employee_id)
    sync_user(
        employee_id,
        employees_db[employee_id],
        telegram_id=get_telegram_id_by_employee(employee_id),
        phone=contacts_db.get(employee_id, {}).get("phone"),
        role=DB_ROLE_BY_ROLE[role],
    )
    save_database()


@router.message(Command("admin"))
async def cmd_admin(message: types.Message):
    if not is_admin(message.from_user.id):
        await message.answer("❌ Доступ запрещен")
        return
    try:
        args = shlex.split(message.text)
    except ValueError:
        await message.answer("❌ Проверьте кавычки в команде")
        return
    if len(args) < 2:
        await message.answer(
            "🛠 Админ-панель:\n"
            "/admin add ТБ001 \"Иванов Иван Иванович\" - добавить сотрудника\n"
            "/admin set_role ТБ001 employee|manager|admin - назначить роль\n"
            "/admin add_manager ТБ001 - назначить руководителя\n"
            "/admin list - список всех сотрудников\n"
            "/admin managers - список руководителей\n"
            "/admin admins - список администраторов\n"
            "/admin remove ТБ001 - удалить сотрудника\n"
            "/admin remove_manager ТБ001 - снять права руководителя\n"
            "/admin users - список привязанных аккаунтов\n"
            "/admin export - экспорт всех заявок"
        )
        return
    command = args[1]
    if command in ("add", "add_employee") and len(args) >= 4:
        employee_id = args[2]
        full_name = " ".join(args[3:])
        if employee_id in employees_db:
            await message.answer(f"❌ Сотрудник с табельным номером {employee_id} уже существует")
        else:
            employees_db[employee_id] = full_name
            set_employee_role(employee_id, ROLE_EMPLOYEE)
            sync_user(employee_id, full_name, role=DB_ROLE_EMPLOYEE)
            save_database()
            save_data(EMPLOYEES_FILE, employees_db)
            await message.answer(f"✅ Сотрудник добавлен:\nТабельный: {employee_id}\nФИО: {full_name}")
    elif command == "add_manager" and len(args) >= 3:
        employee_id = args[2]
        if employee_id not in employees_db:
            await message.answer(f"❌ Сотрудник с табельным номером {employee_id} не найден")
            return
        manager_data = add_manager(employee_id)
        sync_employee_role_to_database(employee_id)
        await message.answer(
            "✅ Руководитель добавлен:\n"
            f"ФИО: {manager_data['full_name']}\n"
            f"Табельный: {employee_id}"
        )
    elif command == "set_role" and len(args) >= 4:
        employee_id = args[2]
        role = normalize_role(args[3])
        if employee_id not in employees_db:
            await message.answer(f"❌ Сотрудник с табельным номером {employee_id} не найден")
            return
        if not role:
            await message.answer("❌ Роль должна быть employee, manager или admin")
            return
        set_employee_role(employee_id, role)
        sync_employee_role_to_database(employee_id)
        await message.answer(
            "✅ Роль сотрудника обновлена:\n"
            f"Табельный: {employee_id}\n"
            f"ФИО: {employees_db[employee_id]}\n"
            f"Роль: {get_role_text(role)}"
        )
    elif command == "list":
        if not employees_db:
            await message.answer("📋 Список сотрудников пуст")
        else:
            response = "📋 Список сотрудников:\n\n"
            for emp_id, name in employees_db.items():
                is_linked = "✅" if any(e == emp_id for e in users_db.values()) else "❌"
                response += f"{is_linked} {emp_id}: {name} — {get_role_text(get_employee_role(emp_id))}\n"
            await message.answer(response)
    elif command == "managers":
        managers = list_employees_by_role(ROLE_MANAGER)
        if not managers:
            await message.answer("👔 Список руководителей пуст")
        else:
            response = "👔 Руководители:\n\n"
            for employee_id, full_name in managers:
                telegram_id = get_telegram_id_by_employee(employee_id) or "не привязан"
                response += f"👤 {full_name}\n"
                response += f"   Табельный: {employee_id}\n"
                response += f"   Telegram ID: {telegram_id}\n\n"
            await message.answer(response)
    elif command == "admins":
        admins = list_employees_by_role(ROLE_ADMIN)
        if not admins:
            await message.answer("🛠 Список администраторов пуст")
        else:
            response = "🛠 Администраторы:\n\n"
            for employee_id, full_name in admins:
                telegram_id = get_telegram_id_by_employee(employee_id) or "не привязан"
                response += f"👤 {full_name}\n"
                response += f"   Табельный: {employee_id}\n"
                response += f"   Telegram ID: {telegram_id}\n\n"
            await message.answer(response)
    elif command == "remove" and len(args) >= 3:
        employee_id = args[2]
        if employee_id in employees_db:
            del employees_db[employee_id]
            for tg_id, emp_id in list(users_db.items()):
                if emp_id == employee_id:
                    del users_db[tg_id]
            if employee_id in orders_db:
                del orders_db[employee_id]
            if employee_id in reminders_db:
                del reminders_db[employee_id]
            if employee_id in contacts_db:
                del contacts_db[employee_id]
                save_data(CONTACTS_FILE, contacts_db)
            delete_employee_role(employee_id)
            save_data(EMPLOYEES_FILE, employees_db)
            save_data(USERS_FILE, users_db)
            save_data(ORDERS_FILE, orders_db)
            save_data(REMINDERS_FILE, reminders_db)
            await message.answer(f"✅ Сотрудник {employee_id} удален из всех баз")
        else:
            await message.answer(f"❌ Сотрудник с табельным номером {employee_id} не найден")
    elif command == "remove_manager" and len(args) >= 3:
        employee_id = args[2]
        if remove_manager(employee_id):
            sync_employee_role_to_database(employee_id)
            await message.answer(f"✅ Права руководителя сняты с сотрудника {employee_id}")
        else:
            await message.answer(f"❌ Сотрудник {employee_id} не найден или не является руководителем")
    elif command == "users":
        if not users_db:
            await message.answer("📱 Нет привязанных Telegram аккаунтов")
        else:
            response = "📱 Привязанные Telegram аккаунты:\n\n"
            for tg_id, emp_id in users_db.items():
                employee_name = employees_db.get(emp_id, "Неизвестно")
                response += f"👤 {employee_name}\n"
                response += f"   Табельный: {emp_id}\n"
                response += f"   Роль: {get_role_text(get_employee_role(emp_id))}\n"
                response += f"   Telegram ID: {tg_id}\n\n"
            await message.answer(response)
    elif command == "export":
        export_data = {
            "export_date": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "orders": orders_db
        }
        export_filename = os.path.join(
            BASE_DIR,
            f"orders_export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        )
        with open(export_filename, "w", encoding="utf-8") as f:
            json.dump(export_data, f, ensure_ascii=False, indent=2)
        await message.answer_document(
            types.FSInputFile(export_filename),
            caption="📊 Экспорт всех заявок"
        )
