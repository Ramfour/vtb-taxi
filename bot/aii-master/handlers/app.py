from aiogram import Router, types
from aiogram.filters import Command

from config import WEBAPP_URL


router = Router()


@router.message(Command("app"))
async def cmd_app(message: types.Message):
    if not WEBAPP_URL:
        await message.answer(
            "Mini App готов, но для открытия из Telegram нужна публичная HTTPS-ссылка.\n"
            "Добавьте WEBAPP_URL в .env и перезапустите бота."
        )
        return

    keyboard = types.InlineKeyboardMarkup(
        inline_keyboard=[
            [
                types.InlineKeyboardButton(
                    text="Открыть приложение",
                    web_app=types.WebAppInfo(url=WEBAPP_URL)
                )
            ]
        ]
    )
    await message.answer("Откройте Mini App:", reply_markup=keyboard)
