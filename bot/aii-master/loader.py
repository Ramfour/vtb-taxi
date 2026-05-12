from aiogram import Bot, Dispatcher
from aiogram.client.session.aiohttp import AiohttpSession
from aiogram.fsm.storage.memory import MemoryStorage
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from config import BOT_TOKEN, TELEGRAM_PROXY, TELEGRAM_REQUEST_TIMEOUT


session = AiohttpSession(
    proxy=TELEGRAM_PROXY,
    timeout=TELEGRAM_REQUEST_TIMEOUT
)
bot = Bot(token=BOT_TOKEN, session=session)
dp = Dispatcher(storage=MemoryStorage())
scheduler = AsyncIOScheduler()
