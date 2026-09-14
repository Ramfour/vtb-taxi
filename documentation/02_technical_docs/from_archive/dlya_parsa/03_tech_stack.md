# Стек технологий

## Backend

- **PHP**: 8.3+
- **Laravel Framework**: 13.x
- **Eloquent ORM** + миграции
- **Blade**: серверный рендеринг шаблонов

### Ключевые пакеты (dev)

- PHPUnit 12
- Laravel Pint (форматирование)
- Collision, Pail, Faker

## Frontend

- **Vite** (сборка)
- **Tailwind CSS v4** (через `@tailwindcss/vite`)
- Небольшой JS для интерактивных частей (без тяжелых SPA-фреймворков)

## Хранение и инфраструктура

- **MySQL**: основная БД
- **Файловая система** (`storage/`):
  - сессии (при `SESSION_DRIVER=file`)
  - логи `storage/logs`
  - экспорт CSV (в `storage/app/...`, если доступно)
- **Cron + Laravel Scheduler**:
  - `schedule:run` запускается cron-ом
  - внутри планируются задачи (например `taxi:cleanup`)

## Локальная разработка

- **Docker Compose**:
  - nginx (web)
  - php-fpm (php)
  - mysql 8.4 (db)
  - redis (опционально)
  - phpMyAdmin

Файлы:

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/nginx/default.conf`

## Продакшн-хостинг (типовой)

Возможен запуск без Docker на shared/VPS-хостинге:

- Web server: nginx/Apache (обычно FastCGI)
- PHP 8.3 (важно: CLI и web должны совпадать по версии)
- MySQL (локально на хостинге)
- Cron (для scheduler)
- SSL (Let’s Encrypt или другой DV)

