# База данных

СУБД: MySQL.

Основные таблицы создаются миграциями в `database/migrations`.

## users

Пользователи системы (сотрудники/руководители/админ).

Ключевые поля:

- `id` (PK)
- `full_name`
- `employee_number` (уникальный табельный номер)
- `phone`
- `password` (bcrypt, через `casts: password => hashed`)
- `role` (enum `App\Enums\UserRole`)
- `is_active`
- `telegram_id` (уникальный, опционально)
- `do_not_disturb_until`
- `remember_token`
- `created_at`, `updated_at`, `deleted_at` (soft delete)

Индексы:

- `unique(employee_number)`
- `unique(telegram_id)`
- `index(is_active, role)`

## invitations

Приглашения на регистрацию (создаются руководителем/админом).

Ключевые поля:

- `id` (PK)
- `token` (уникальный, 64)
- `employee_number`
- `role` (какую роль выдаем при регистрации по приглашению)
- `created_by` (FK -> `users.id`)
- `used_by` (FK -> `users.id`, nullable)
- `is_used`, `used_at`, `expires_at`
- `created_at`, `updated_at`

Индексы:

- `unique(token)`
- `index(employee_number, is_used)`

## temp_requests

Буфер заявок (слой “на согласовании”).

Ключевые поля:

- `id` (PK)
- `user_id` (FK -> `users.id`)
- `full_name`, `phone`, `address_raw`
- `address_norm` (нормализованный адрес, если используется)
- `lat`, `lon` (координаты, nullable)
- `date_time` (время подачи/поездки)
- `status` (enum `App\Enums\RequestStatus`, по умолчанию `Pending`)
- `reviewed_by` (FK -> `users.id`, nullable)
- `reviewed_at`, `cancelled_at`
- `manager_comment`, `rejection_reason`
- `created_at`, `updated_at`, `deleted_at` (soft delete)

Индексы/ограничения:

- `unique(user_id, date_time)` (не допускаем две заявки одного пользователя на одно время)
- `index(status)`, `index(date_time)`, `index(status, date_time)`

## requests

Финальный слой заявок (источник CSV-выгрузки).

Ключевые поля:

- `id` (PK)
- `user_id` (FK -> `users.id`)
- `full_name`, `phone`, `address_raw`
- `address_norm`, `lat`, `lon`
- `date_time`
- `status` (по умолчанию `Approved`)
- `approved_by` (FK -> `users.id`, nullable)
- `approved_at` (nullable)
- `temp_request_id` (FK -> `temp_requests.id`, nullable)
- `exported_at` (nullable) — момент, когда запись попала в выгрузку CSV (используется для авто-очистки)
- `cancelled_at`, `rejection_reason`
- `created_at`, `updated_at`, `deleted_at` (soft delete)

Индексы/ограничения:

- `unique(user_id, date_time)`
- `index(status)`, `index(date_time)`, `index(status, date_time)`
- `index(exported_at)`

## audit_logs

Журнал действий (аудит). В отличие от заявок, тут нет soft-delete, это именно “лог”:

- `id` (PK)
- `user_id` (FK -> `users.id`, nullable)
- `action` (строка-ключ действия)
- `entity_type` (тип сущности, например `TempRequest`, `Request`, `User`)
- `entity_id` (nullable)
- `old_values` (JSON)
- `new_values` (JSON)
- `ip_address`, `user_agent`
- `created_at` (timestamp, по умолчанию `CURRENT_TIMESTAMP`)

Индексы:

- `index(entity_type, entity_id)`
- `index(action)`

## notifications

Уведомления (пока используется как инфраструктура/заготовка).

- `id` (PK)
- `user_id` (FK -> `users.id`)
- `type` (строка)
- `payload` (JSON)
- `status` (enum `App\Enums\NotificationStatus`)
- `sent_at`
- `created_at`, `updated_at`

Индексы:

- `index(user_id, status)`
- `index(type)`

## user_addresses

История адресов сотрудника (для удобства повторного выбора адреса).

- `id` (PK)
- `user_id` (FK -> `users.id`)
- `address` (text)
- `created_at`, `updated_at`

Индексы:

- `index(user_id, created_at)`

## Связи (кратко)

- `users 1 -> N temp_requests`
- `users 1 -> N requests`
- `temp_requests 1 -> 0..1 requests` (через `requests.temp_request_id`)
- `users 1 -> N invitations(created_by)`
- `users 1 -> N invitations(used_by)`
- `users 1 -> N audit_logs`
- `users 1 -> N notifications`
- `users 1 -> N user_addresses`

## Политика хранения (retention)

Реализована команда очистки `taxi:cleanup`, которая удаляет данные по срокам из `config/taxi.php`.

