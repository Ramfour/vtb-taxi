# Архитектура

Проект: корпоративное такси (VTB Taxi). Система состоит из двух равноправных интерфейсов — веб-портала (Laravel + Blade) и Telegram-бота (Python/aiogram) — и единой MySQL-базы данных.

Оба интерфейса предоставляют **одинаковый набор действий** для всех ролей: сотрудник, руководитель, администратор. Выбор интерфейса — личное предпочтение пользователя.

## Роли и доступ

- **Employee (Сотрудник)**: подает заявки (создается запись в `temp_requests`), может отменять свои заявки.
- **Manager (Руководитель)**: видит заявки на согласовании, одобряет/отклоняет, переносит одобренные в финальный слой.
- **Admin (Администратор)**: все возможности руководителя + управление пользователями/приглашениями + аудит.

Доступ контролируется middleware:

- `EnsureUserHasRole` (`role` в роутерах): ограничение страниц по роли.
- `ResolveActingUser` (`resolve.actor`): выбор “действующего пользователя” (если используется сценарий имперсонации/переключения).

## Основной поток данных (заявки)

1. **Сотрудник подает заявку**:
   - `POST` в контроллер сотрудника.
   - Сервис `RequestWorkflowService::createTempRequest()`:
     - нормализует телефон (замена `+7…` на `8…`),
     - проверяет на дубликат (на одного пользователя и время),
     - создает `TempRequest` со статусом `Pending`,
     - запоминает адрес в `user_addresses` (история адресов),
     - пишет запись в `audit_logs`.

2. **Руководитель рассматривает**:
   - либо точечно (approve/reject),
   - либо массово (selected / all_pending).
   - При рассмотрении:
     - статус заявки меняется на `Approved` или `Rejected`,
     - заполняются `reviewed_by`, `reviewed_at`,
     - фиксируется комментарий/причина,
     - пишется аудит.

3. **Перенос одобренных в финальный слой** (перед выгрузкой):
   - `RequestWorkflowService::finalizeApprovedRequests()` переносит одобренные `temp_requests` в `requests`:
     - создается `Request` (финальная заявка) со статусом `Approved`,
     - `temp_request_id` связывает финальную заявку с буферной,
     - `approved_by`/`approved_at` проставляются,
     - буферная запись удаляется через soft-delete,
     - пишется аудит.

4. **Выгрузка CSV**:
   - `ManagerPortalController::exportCsv()`:
     - берет данные через `RequestWorkflowService::exportFinalRequests(from, to)`,
     - формирует CSV,
     - помечает выгруженные записи `requests.exported_at = now()` (для последующей авто-очистки),
     - сохраняет копию CSV на диске (если доступно) и/или отдает файл на скачивание,
     - пишет аудит.

## Аудит

Все ключевые действия пишутся через `AuditLogger` в таблицу `audit_logs`:

- регистрация пользователей,
- использование приглашений,
- создание/рассмотрение/перенос заявок,
- выгрузка CSV,
- операции админа (удаления/управление и т.п.).

## Авто-очистка данных (retention)

Добавлена команда:

- `php artisan taxi:cleanup` (есть `--dry-run`)

Политика хранения задается в `config/taxi.php` (можно переопределять через `.env`):

- `temp_requests`: force-delete soft-deleted старше 7 дней,
- `temp_requests`: force-delete rejected/cancelled/expired старше 7 дней,
- `requests`: force-delete через 5 дней после `exported_at`,
- `requests`: force-delete soft-deleted старше 5 дней,
- `audit_logs`: удаление записей старше 90 дней.

Запуск по расписанию:

- `routes/console.php` содержит `Schedule::command('taxi:cleanup')->dailyAt('03:30');`
- Для автономной работы на хостинге нужен cron, который запускает `php artisan schedule:run` (обычно раз в минуту).

## Telegram-бот

Бот (`bot/aii-master`, Python/aiogram) — второй равноправный интерфейс системы.

Общение с данными происходит **через Laravel HTTP API** (`/api/bot/*`), а не напрямую с БД. Бот отправляет запросы с заголовками `X-Bot-Token` (секрет) и `X-Telegram-Id` (идентификатор пользователя), Laravel выполняет бизнес-логику и пишет в MySQL.

```
[Telegram Bot] → HTTPS → [Laravel API /api/bot/*] → [MySQL]
[Web Browser]  → HTTPS → [Laravel Web /employee/*, /manager/*] → [MySQL]
```

Доступные действия через бота:

- **Сотрудник**: авторизация по табельному номеру, создание заявки, просмотр своих заявок, отмена.
- **Руководитель**: просмотр очереди на согласование, одобрение/отклонение заявок.
- **Администратор**: все возможности руководителя (через бота).

Подробнее: `documentation/07_bot_api_integration.md`.

## Слои приложения

- **UI (Web)**: Blade-шаблоны + CSS/JS (Vite/Tailwind).
- **UI (Bot)**: Telegram-бот (Python/aiogram) — через Laravel API.
- **HTTP**: контроллеры `Employee*`, `Manager*`, `Auth*`, `AccountController`, `BotAuthController`.
- **Domain/Service layer**: `RequestWorkflowService`, `AuditLogger`.
- **Data**: Eloquent модели (`User`, `TempRequest`, `Request`, `Invitation`, `AuditLog`, `Notification`, `UserAddress`).
- **Infra**: MySQL (основные данные), файловая система (экспорты/сессии), cron (scheduler).

