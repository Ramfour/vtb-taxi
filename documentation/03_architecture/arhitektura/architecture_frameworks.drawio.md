# Легенда: architecture_frameworks.drawio

Файл: `_about/architecture_frameworks.drawio` — “карта стека”: ключевые фреймворки/инструменты и их связи.

## Элементы схемы

- **Laravel 13**: backend-фреймворк (routing, Blade, Eloquent, migrations, scheduler).
- **PHP 8.3**: язык/рантайм (CLI и web).
- **MySQL**: основная БД.
- **nginx/Apache**: веб-сервер, который отдает сайт.
- **Vite**: сборка фронтенда.
- **Tailwind CSS v4**: стили.
- **Blade templates**: UI-шаблоны.
- **Docker Compose (dev)**: локальный dev-стек (nginx+php+mysql+redis+phpMyAdmin).

## Термины

- **Framework**: набор библиотек/правил для разработки (Laravel).
- **Runtime**: среда выполнения (PHP).
- **CA (Certificate Authority)**: удостоверяющий центр (например Let’s Encrypt).
- **Dev vs Prod**:
  - **Dev**: часто Docker для одинаковой среды.
  - **Prod**: на shared-хостинге Docker обычно нет, работает webserver+PHP+MySQL+cron.

## Заметка по версиям PHP

Важно, чтобы:

- веб-сервер обслуживал приложение на PHP 8.3,
- CLI (shell) запускал artisan тоже на PHP 8.3 (иначе будут ошибки зависимостей Composer).

