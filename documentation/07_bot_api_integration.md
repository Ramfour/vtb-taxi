# Интеграция Telegram-бота с Laravel через API

## Проблема (было)

Telegram-бот (`bot/aii-master`) работал **полностью изолированно** от MySQL-базы Laravel.
Вместо единой БД у него была своя параллельная система хранения на JSON-файлах:

| JSON-файл | Что хранил |
|---|---|
| `employees.json` | Справочник `{employee_id: full_name}` |
| `users.json` | Связка `{telegram_id: employee_id}` |
| `orders.json` | Заявки в собственном формате |
| `employee_roles.json` | Роли сотрудников |
| `contacts.json` | Телефоны и адреса |
| `database.json` | Полная копия структуры MySQL в JSON |

Сервис `services/db_compat.py` был полным эмулятором MySQL-структуры: он
воспроизводил те же таблицы (`users`, `temp_requests`, `requests`, `audit_logs` и т.д.)
и при старте мигрировал данные из старых JSON в `database.json`.

**Следствие:** заявки из бота не попадали в MySQL и не были видны в веб-интерфейсе.
Заявки из веба бот не видел. Данные о пользователях были в двух несинхронизированных
местах.

## Решение (стало)

Бот работает через **HTTP API Laravel**. Все операции с данными выполняет Laravel
и пишет в единую MySQL-базу. Бот только отправляет запросы и отображает результат.

```
[Telegram Bot (Python)] → HTTPS → [Laravel API] → [MySQL]
```

JSON-файлы остаются как **fallback**: если `LARAVEL_API_URL` или `BOT_API_SECRET`
не заданы, бот работает в старом режиме (локально, без синхронизации).

## Архитектура интеграции

### Аутентификация запросов бота

Каждый запрос от бота содержит два заголовка:

- `X-Bot-Token: <BOT_API_SECRET>` — общий секрет, подтверждает что запрос от бота.
- `X-Telegram-Id: <telegram_id>` — идентифицирует пользователя, от имени которого действует бот.

Laravel middleware `BotAuth` (`app/Http/Middleware/BotAuth.php`):
1. Проверяет `X-Bot-Token` против `config('taxi.bot_api_secret')`.
2. Ищет пользователя в `users` по `telegram_id`.
3. Устанавливает его как текущего авторизованного пользователя.

Далее обычный `role`-middleware проверяет права (employee/manager/admin).

### API-маршруты бота (`routes/api.php`)

Все маршруты под префиксом `/api/bot/` и middleware `bot.auth`:

| Метод | URL | Описание |
|---|---|---|
| `POST` | `/api/bot/auth/link` | Привязать telegram_id к employee_number |
| `GET` | `/api/bot/me` | Профиль текущего пользователя |
| `POST` | `/api/bot/auth/unlink` | Отвязать telegram_id |
| `GET` | `/api/bot/employee/requests` | Мои заявки (буфер + финальный слой) |
| `POST` | `/api/bot/employee/requests` | Создать заявку |
| `PATCH` | `/api/bot/employee/requests/{id}/cancel` | Отменить заявку |
| `GET` | `/api/bot/manager/requests` | Очередь согласования (pending) |
| `PATCH` | `/api/bot/manager/requests/{id}/review` | Одобрить/отклонить заявку |
| `POST` | `/api/bot/manager/requests/finalize` | Перенести одобренные в финальный слой |

Все маршруты используют **те же контроллеры** что и веб-интерфейс
(`EmployeeRequestController`, `ManagerRequestController`, `BotAuthController`).
Бизнес-логика не дублируется.

### Клиент в боте (`bot/aii-master/services/api_client.py`)

Асинхронный HTTP-клиент на `httpx`. Функции:

- `link_telegram(employee_number, telegram_id)` — авторизация
- `unlink_telegram(telegram_id)` — выход
- `get_me(telegram_id)` — профиль
- `create_request(telegram_id, ...)` — создать заявку
- `get_my_requests(telegram_id)` — список заявок сотрудника
- `cancel_request(telegram_id, id, reason)` — отменить заявку
- `get_pending_requests(telegram_id)` — очередь для руководителя
- `review_request(telegram_id, id, action, ...)` — одобрить/отклонить
- `finalize_requests(telegram_id)` — финализировать
- `is_configured()` — проверить наличие настроек (fallback-условие)

## Настройка

### 1. Laravel (`.env`)

```env
BOT_API_SECRET=сгенерированный_секрет_64_символа
```

Генерация секрета:
```bash
php artisan tinker --execute="echo Str::random(64);"
```

### 2. Бот (`.env` в `bot/aii-master/`)

```env
LARAVEL_API_URL=https://vtb-taxi.ru
BOT_API_SECRET=тот_же_секрет_что_и_в_laravel
```

### 3. Зависимости бота

```bash
pip install httpx>=0.27.0
```

Или через `requirements.txt` (уже добавлено):
```bash
pip install -r requirements.txt
```

### 4. Привязка пользователей

При первом запуске `/start` в боте и прохождении авторизации (табельный номер +
телефон) бот вызывает `POST /api/bot/auth/link`, который записывает `telegram_id`
в поле `users.telegram_id` в MySQL. После этого пользователь идентифицируется
по `telegram_id` при каждом запросе.

**Важно:** пользователь должен существовать в MySQL (создан через веб-интерфейс
по приглашению). Бот не создаёт новых пользователей самостоятельно.

## Что осталось в JSON-файлах (fallback)

Если `LARAVEL_API_URL` или `BOT_API_SECRET` не заданы (`is_configured()` возвращает
`False`), бот работает в старом режиме через `db_compat.py` и JSON-файлы.
Это полезно для локальной разработки без Laravel.

При включённой интеграции JSON-файлы по-прежнему используются для:

- `users.json` — локальный кеш связки `{telegram_id: employee_id}` (для `require_auth`)
- `contacts.json` — сохранённый адрес и телефон (для подстановки при повторном заказе)
- `reminders.json` — напоминания (не интегрированы с Laravel API)

## Запуск через Docker

### Схема контейнеров

```text
[bot] ──HTTP──► [webserver: nginx:80] ──FastCGI──► [php:9000]
                                                        │
                                                    [mysql]
                                                    [redis]
```

Бот обращается к nginx **внутри Docker-сети** по имени сервиса `webserver` —
внешний URL не нужен. `LARAVEL_API_URL=http://webserver` переопределяется
в `docker-compose.yml` через `environment`, поэтому в `.env` бота его указывать
не обязательно (актуально только для запуска без Docker).

### Шаг 1 — Создать `.env` для бота

Создать `bot/aii-master/.env` на основе `bot/aii-master/.env.example`:

```env
BOT_TOKEN=123456789:AAF...ваш_токен
BOT_API_SECRET=тот_же_секрет_что_в_laravel
ADMIN_IDS=ВАШ_TELEGRAM_ID
YANDEX_GEOCODER_API_KEY=
```

### Шаг 2 — Добавить `BOT_API_SECRET` в Laravel `.env`

```env
BOT_API_SECRET=тот_же_секрет_что_у_бота
```

Сгенерировать секрет (выполнить один раз):

```bash
docker compose run --rm php php artisan tinker --execute="echo Str::random(64);"
```

### Шаг 3 — Собрать и запустить

```bash
# Собрать образы и запустить все сервисы
docker compose up -d --build

# Миграции БД (один раз при первом запуске)
docker compose exec php php artisan migrate --force

# Проверить логи бота
docker compose logs -f bot
```

### Шаг 4 — Проверить

```bash
# Все контейнеры должны быть в статусе Up
docker compose ps

# В логах бота должна быть строка:
# "Laravel API mode: http://webserver — JSON fallback disabled."
docker compose logs bot | grep "Laravel API mode"
```

### Что добавлено в Docker-файлы

- `docker/bot/Dockerfile` — образ бота (Python 3.11 slim)
- `docker-compose.yml` — сервис `bot` + volume `bot_data`
- `bot/aii-master/.env.example` — шаблон переменных окружения бота

### Продакшн на VPS

**Вариант А (рекомендуется) — бот и Laravel на одном VPS в Docker:**
`LARAVEL_API_URL=http://webserver` работает внутри сети без изменений.

**Вариант Б — бот на отдельном сервере:**
В `.env` бота на том сервере указать внешний URL:

```env
LARAVEL_API_URL=https://vtb-taxi.ru
BOT_API_SECRET=секрет
```

И запускать через `python bot.py` или отдельным `docker run`.

## Нерешённые задачи (TODO)

- Напоминания (`reminders.py`) работают только через JSON, не синхронизированы с Laravel.
- Редактирование заявки руководителем (`edit_order`) использует fallback — в Laravel API
  нет отдельного эндпоинта редактирования через бот (можно добавить `UpdateFinalRequest`).
- При деактивации пользователя в веб-интерфейсе бот не уведомляется — пользователь
  получит 401/404 при следующем запросе.
