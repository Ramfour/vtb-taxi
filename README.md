# Корпоративное такси — ВТБ Новосибирск

Система автоматизации заказа корпоративного такси для сотрудников отдела дистанционного клиентского обслуживания ПАО «ВТБ» (г. Новосибирск).

Дипломная работа. Веб-часть (PHP/Laravel + фронтенд) и аналитика — авторская разработка. Telegram-бот (`bot/`) — отдельный сервис, разработан коллегой.

---

## Что делает система

Сотрудники подают заявки на корпоративное такси через веб-портал или Telegram-бот. Руководитель согласует заявки, администратор управляет пользователями и выгружает данные для перевозчика в CSV.

**Роли:**
- **Employee (Сотрудник)** — создаёт и отменяет свои заявки
- **Manager (Руководитель)** — согласует/отклоняет заявки, переносит одобренные в финальный слой, выгружает CSV
- **Admin (Администратор)** — все возможности руководителя + управление пользователями, приглашениями, аудит

---

## Архитектура

```
[Браузер]        → HTTPS → [Laravel (PHP)]  → [MySQL]
[Telegram-бот]   → HTTPS → [Laravel API]    → [MySQL]
```

- **Модульный монолит** на Laravel 13.x
- **Двухслойная обработка заявок**: буфер (`temp_requests`) → финальный слой (`requests`)
- **Единая БД** для обоих интерфейсов — бот работает через HTTP API, не напрямую с MySQL
- **Аудит** всех значимых действий в `audit_logs`
- **Авто-очистка** старых данных через Laravel Scheduler (`php artisan taxi:cleanup`)

---

## Стек технологий

| Компонент | Технологии |
|---|---|
| Backend | PHP 8.3+, Laravel 13.x, Eloquent ORM |
| Frontend | Blade, Tailwind CSS v4, Vite |
| База данных | MySQL 8.4 |
| Telegram-бот | Python 3.11+, aiogram 3.x, httpx |
| Инфраструктура | Docker, Nginx, Laravel Scheduler |

---

## Структура репозитория

```
/
├── app/                    # PHP-код: контроллеры, модели, сервисы
│   ├── Http/               # Контроллеры и middleware
│   ├── Models/             # Eloquent-модели
│   └── Services/           # RequestWorkflowService, AuditLogger
├── bot/aii-master/         # Telegram-бот (Python/aiogram) — отдельный сервис
├── config/                 # Конфигурация Laravel (taxi.php и др.)
├── database/               # Миграции и сидеры
├── documentation/          # Техническая документация и материалы диплома
├── docker/                 # Dockerfile-ы для nginx, php-fpm, бота
├── public/                 # Точка входа (index.php), собранные ассеты
├── resources/              # Blade-шаблоны, CSS, JS (исходники)
├── routes/                 # web.php, api.php, console.php
├── docker-compose.yml      # Оркестрация для локальной разработки
└── .env.example            # Шаблон переменных окружения
```

---

## Быстрый старт (Docker)

### 1. Клонировать и настроить окружение

```bash
git clone <url>
cd vtb-taxi.local

# Laravel
cp .env.example .env

# Бот
cp bot/aii-master/.env.example bot/aii-master/.env
```

### 2. Заполнить `.env`

```env
DB_PASSWORD=ваш_пароль

# Генерируется один раз после шага 3:
BOT_API_SECRET=
```

Заполнить `bot/aii-master/.env`:

```env
BOT_TOKEN=токен_из_BotFather
BOT_API_SECRET=тот_же_секрет_что_в_laravel
ADMIN_IDS=ваш_telegram_id
```

### 3. Собрать и запустить

```bash
docker compose up -d --build

# Миграции (один раз)
docker compose exec php php artisan migrate --force

# Сгенерировать APP_KEY
docker compose exec php php artisan key:generate

# Сгенерировать BOT_API_SECRET и вставить в оба .env
docker compose run --rm php php artisan tinker --execute="echo Str::random(64);"
```

### 4. Проверить

```bash
docker compose ps
# Все сервисы должны быть Up

docker compose logs -f bot
# Должна быть строка: "Laravel API mode: http://webserver — JSON fallback disabled."
```

Веб-интерфейс доступен на `http://localhost` (или настроенном домене).

---

## Запуск без Docker (shared/VPS хостинг)

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm ci && npm run build
```

Настроить cron для планировщика (раз в минуту):

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Бот запускается отдельно:

```bash
cd bot/aii-master
pip install -r requirements.txt
python bot.py
```

В `.env` бота указать внешний URL Laravel:

```env
LARAVEL_API_URL=https://vtb-taxi.ru
```

---

## Интеграция бота с Laravel

Бот обращается к Laravel через REST API (`/api/bot/*`). Каждый запрос содержит:
- `X-Bot-Token` — общий секрет (`BOT_API_SECRET`)
- `X-Telegram-Id` — Telegram ID пользователя

Laravel выполняет всю бизнес-логику и пишет в MySQL. Бот не имеет собственной базы данных.

Подробнее: [`documentation/02_technical_docs/07_bot_api_integration.md`](documentation/02_technical_docs/07_bot_api_integration.md)

---

## Переменные окружения

| Переменная | Описание |
|---|---|
| `APP_KEY` | Ключ шифрования Laravel (генерируется `artisan key:generate`) |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Подключение к MySQL |
| `BOT_API_SECRET` | Общий секрет для аутентификации запросов от бота |
| `TAXI_TEMP_SOFT_DELETED_DAYS` | Хранение удалённых буферных заявок (дней, по умолчанию 7) |
| `TAXI_AUDIT_DAYS` | Хранение записей аудита (дней, по умолчанию 90) |

Полный список — в `.env.example`.

---

## Документация

Техническая документация находится в папке [`documentation/`](documentation/):

- [`02_technical_docs/01_architecture.md`](documentation/02_technical_docs/01_architecture.md) — архитектура системы
- [`02_technical_docs/02_database.md`](documentation/02_technical_docs/02_database.md) — структура базы данных
- [`02_technical_docs/03_tech_stack.md`](documentation/02_technical_docs/03_tech_stack.md) — стек технологий
- [`02_technical_docs/07_bot_api_integration.md`](documentation/02_technical_docs/07_bot_api_integration.md) — интеграция бота
- [`02_technical_docs/05_security.md`](documentation/02_technical_docs/05_security.md) — безопасность

---

## Что попадает в продакшн-репозиторий

В репозиторий включается:

- Весь PHP/Laravel код (`app/`, `config/`, `database/`, `routes/`, `resources/`, `public/`)
- Бот (`bot/aii-master/`) — весь исходный код Python
- Docker-файлы и конфиги (`docker/`, `docker-compose.yml`)
- Документация (`documentation/`)
- Шаблоны окружения (`.env.example`, `bot/aii-master/.env.example`)

**Не попадает в репозиторий** (исключено через `.gitignore`):

- `.env` — секреты и ключи
- `vendor/`, `node_modules/` — зависимости (устанавливаются на месте)
- `public/build/` — собранные ассеты (собираются на месте)
- `storage/logs/`, `storage/app/` — рантайм-данные
- `*.sql`, `*.dump` — дампы базы данных
- `bot/aii-master/.venv/`, `bot/aii-master/__pycache__/` — Python-окружение и кеш
- `site/`, `sql/` — локальные снапшоты с хостинга
