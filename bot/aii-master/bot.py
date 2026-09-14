import asyncio
import logging

from aiogram.exceptions import TelegramNetworkError
from aiogram.types import BotCommand, BotCommandScopeChat, BotCommandScopeDefault

from config import BOT_API_SECRET, LARAVEL_API_URL, POLLING_RETRY_DELAY
from handlers.admin import router as admin_router
from handlers.app import router as app_router
from handlers.auth import router as auth_router
from handlers.manager import router as manager_router
from handlers.orders import router as orders_router
from handlers.reminders import load_active_reminders, router as reminders_router
from loader import bot, dp
from services.db_compat import ensure_database_schema_from_json
from services.order_service import ensure_orders_schema
from services.order_cleanup import schedule_order_cleanup
from services.roles import ensure_roles_schema, get_admin_telegram_ids, get_manager_telegram_ids
from storage import init_files
from webapp.server import start_webapp_server


logging.basicConfig(level=logging.INFO)


def register_routers():
    dp.include_router(auth_router)
    dp.include_router(app_router)
    dp.include_router(orders_router)
    dp.include_router(reminders_router)
    dp.include_router(manager_router)
    dp.include_router(admin_router)


async def setup_bot_commands():
    user_commands = [
        BotCommand(command="start", description="Запустить бота / авторизация"),
        BotCommand(command="app", description="Открыть Mini App"),
        BotCommand(command="order", description="Оставить заявку на такси"),
        BotCommand(command="reminder", description="Настроить напоминание"),
        BotCommand(command="my_orders", description="Мои заявки"),
        BotCommand(command="logout", description="Выйти из аккаунта"),
    ]
    await bot.set_my_commands(user_commands, scope=BotCommandScopeDefault())

    manager_commands = [
        *user_commands,
        BotCommand(command="manager", description="Согласование заявок"),
    ]
    admin_commands = [
        *manager_commands,
        BotCommand(command="admin", description="Админ-панель"),
    ]
    for manager_id in get_manager_telegram_ids():
        if manager_id.isdigit():
            await bot.set_my_commands(
                manager_commands,
                scope=BotCommandScopeChat(chat_id=int(manager_id))
            )
    for admin_id in get_admin_telegram_ids():
        if admin_id.isdigit():
            await bot.set_my_commands(
                admin_commands,
                scope=BotCommandScopeChat(chat_id=int(admin_id))
            )


async def main():
    api_mode = bool(LARAVEL_API_URL and BOT_API_SECRET)
    init_files()
    ensure_roles_schema()
    if not api_mode:
        ensure_orders_schema()
        ensure_database_schema_from_json()
    else:
        logging.info("Laravel API mode: %s — JSON fallback disabled.", LARAVEL_API_URL)
    register_routers()
    load_active_reminders()
    schedule_order_cleanup()
    webapp_runner = await start_webapp_server()
    try:
        while True:
            try:
                await setup_bot_commands()
                await dp.start_polling(bot)
                break
            except TelegramNetworkError as e:
                logging.warning(
                    "Не удалось подключиться к Telegram API: %s. "
                    "Повторная попытка через %s секунд.",
                    e,
                    POLLING_RETRY_DELAY
                )
                await asyncio.sleep(POLLING_RETRY_DELAY)
    finally:
        await webapp_runner.cleanup()


if __name__ == "__main__":
    asyncio.run(main())
