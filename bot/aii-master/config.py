import os


BASE_DIR = os.path.dirname(os.path.abspath(__file__))


def load_env(filename=None):
    filename = filename or os.path.join(BASE_DIR, ".env")
    try:
        with open(filename, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, value = line.split("=", 1)
                key = key.strip()
                value = value.strip().strip('"').strip("'")
                os.environ.setdefault(key, value)
    except FileNotFoundError:
        pass


load_env()

BOT_TOKEN = os.getenv("BOT_TOKEN")
YANDEX_GEOCODER_API_KEY = os.getenv("YANDEX_GEOCODER_API_KEY", "")
TELEGRAM_PROXY = os.getenv("TELEGRAM_PROXY") or None
TELEGRAM_REQUEST_TIMEOUT = float(os.getenv("TELEGRAM_REQUEST_TIMEOUT", "90"))
POLLING_RETRY_DELAY = int(os.getenv("POLLING_RETRY_DELAY", "10"))
WEBAPP_HOST = os.getenv("WEBAPP_HOST", "127.0.0.1")
WEBAPP_PORT = int(os.getenv("WEBAPP_PORT", "8088"))
WEBAPP_URL = os.getenv("WEBAPP_URL", "").rstrip("/")
WEBAPP_DEV_TELEGRAM_ID = os.getenv("WEBAPP_DEV_TELEGRAM_ID", "")
ADMIN_IDS = [
    admin_id.strip()
    for admin_id in os.getenv("ADMIN_IDS", "ВАШ_TELEGRAM_ID").split(",")
    if admin_id.strip()
]
ADMIN_EMPLOYEE_IDS = [
    employee_id.strip()
    for employee_id in os.getenv("ADMIN_EMPLOYEE_IDS", "").split(",")
    if employee_id.strip()
]

if not BOT_TOKEN:
    raise RuntimeError("BOT_TOKEN не задан. Добавьте его в .env")

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "").rstrip("/")
BOT_API_SECRET = os.getenv("BOT_API_SECRET", "")

EMPLOYEES_FILE = os.path.join(BASE_DIR, "employees.json")
USERS_FILE = os.path.join(BASE_DIR, "users.json")
ORDERS_FILE = os.path.join(BASE_DIR, "orders.json")
REMINDERS_FILE = os.path.join(BASE_DIR, "reminders.json")
CONTACTS_FILE = os.path.join(BASE_DIR, "contacts.json")
MANAGERS_FILE = os.path.join(BASE_DIR, "managers.json")
ROLES_FILE = os.path.join(BASE_DIR, "employee_roles.json")
DATABASE_FILE = os.path.join(BASE_DIR, "database.json")
