# Легенда: db_schema.drawio

Файл: `_about/db_schema.drawio` — схема таблиц базы данных и связей между ними.

## Как читать обозначения

- **PK**: Primary Key (первичный ключ) — уникальный идентификатор строки.
- **FK**: Foreign Key (внешний ключ) — ссылка на строку в другой таблице.
- **UQ / UNIQUE**: уникальное ограничение.
- **INDEX**: индекс для ускорения выборок.
- **nullable**: поле может быть `NULL`.
- **soft delete**: “мягкое удаление” (поле `deleted_at`), запись скрывается из обычных запросов, но физически остается.

## Таблицы

- **users**: пользователи (сотрудник/руководитель/админ).
- **invitations**: приглашения на регистрацию по токену.
- **temp_requests**: буфер заявок “на согласовании”.
- **requests**: финальные заявки (источник выгрузки).
- **audit_logs**: аудит действий (лог).
- **notifications**: уведомления (заготовка/инфраструктура).
- **user_addresses**: история адресов пользователя.

## Важные поля

- `temp_requests.status` и `requests.status` — enum `RequestStatus`:
  - `Pending=1`, `Approved=2`, `Rejected=3`, `Cancelled=4`, `Expired=5`
- `requests.exported_at` — момент, когда запись попала в CSV (по нему чистим через 5 дней).

## Связи

- `temp_requests.user_id -> users.id`
- `temp_requests.reviewed_by -> users.id` (кто рассмотрел)
- `requests.user_id -> users.id`
- `requests.approved_by -> users.id`
- `requests.temp_request_id -> temp_requests.id` (ссылка на исходную заявку из буфера)
- `audit_logs.user_id -> users.id` (кто совершил действие)
- `notifications.user_id -> users.id`
- `user_addresses.user_id -> users.id`

## Практические замечания

- При удалении пользователей используется soft-delete; при необходимости админ может выполнить force-delete.
- Для долгой эксплуатации важны сроки хранения: буфер/финальные/аудит очищаются командой `taxi:cleanup`.

