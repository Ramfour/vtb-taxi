# Архитектура: Текстовое Описание Диаграмм

В этой папке лежат диаграммы архитектуры проекта “Корпоративное такси” в формате diagrams.net / draw.io (`.drawio`).

Ниже — подробное текстовое описание **каждой** диаграммы: что она показывает и как это связано с реальной реализацией (Laravel + MySQL + хостинг + SSL + cron).

## 1) `architecture_overview.drawio` (Overview)

**Назначение:** самая короткая схема “сверху”, чтобы быстро понять из каких крупных частей состоит система и как они соединены.

**Основные блоки:**

- **Пользователь (Employee / Manager / Admin)**: работает через браузер/телефон.
- **HTTPS + SSL (Let’s Encrypt)**: защищенное соединение; SSL закрывает “Подключение не защищено”.
- **Laravel App (routes, controllers, services) + Blade UI**: монолитное веб-приложение.
- **MySQL**: основное хранилище данных.
- **Storage (filesystem) sessions/logs/exports**: файловое хранилище на сервере: сессии, логи и выгруженные файлы.
- **Cron + Scheduler**: регулярные задачи (Laravel Scheduler).

**Связи/стрелки (что означает поток):**

- Пользователь → HTTPS → Laravel App: все действия (логин, создание заявки, согласование) идут как HTTP(S) запросы.
- Laravel App → MySQL (**SQL**): чтение/запись пользователей, заявок, аудита и т.д.
- Laravel App → Storage (**read/write**): запись сессий, логов, экспортов.
- Cron → Laravel App (**artisan**, пунктир): планировщик на сервере запускает `artisan schedule:run`, а внутри Laravel уже запускаются запланированные задачи (например `taxi:cleanup`).

## 2) `architecture_server.drawio` (Server)

**Назначение:** показать инфраструктурный путь запроса “снаружи внутрь” на обычном хостинге (без Docker).

**Цепочка запроса:**

- **Клиенты (браузер/мобильный)** обращаются к домену `vtb-taxi.ru` / `www`.
- **DNS (A/AAAA, CNAME)** направляет домен на IP хостинга.
- **TLS termination (Let’s Encrypt)** обеспечивает сертификат и HTTPS.
- **Web server (nginx/Apache, FastCGI)** принимает запросы и отдает статические файлы/проксирует PHP.
- **PHP runtime (PHP-FPM/CGI, PHP 8.3)** исполняет PHP.
- **PHP entrypoint `public/index.php`** — точка входа Laravel.

**Где лежит проект и что важно:**

- **Каталог приложения** вида `/var/www/.../vtb-taxi.ru`, при этом **`public/` = docroot** (корень сайта).
- **`storage/`** используется для:
  - `framework/sessions` (сессии, влияет на 419 Page Expired),
  - `logs/laravel.log`,
  - `app/... (exports)` (выгрузки).
- **MySQL** (в схеме указан пример БД `u..._vtb_corporate_taxi`): таблицы `users`, `temp_requests`, `requests`, `audit_logs`, `invitations`, `user_addresses` и т.п.

**Cron и регламентные задачи:**

- **Cron (панель хостинга)**: каждую минуту запускает `artisan schedule:run`.
- **Laravel Scheduler**: внутри приложения запускает ежедневные задачи, например `taxi:cleanup` (в схеме указано “daily 03:30”).

**Бэкапы (рекомендация):** отдельно выделено, что желательно делать бэкапы БД (dump) и, при необходимости, важных файлов (`storage` с экспортами).

## 3) `architecture_backend.drawio` (Backend)

**Назначение:** показать внутреннюю архитектуру Laravel по слоям, как запрос проходит через код и где идет работа с БД/файлами.

**Путь запроса:**

- **HTTP request (GET/POST)** → **Routes**:
  - `routes/web.php` (основной web),
  - `routes/api.php` (если используется).
- **Middleware**:
  - аутентификация/сессии,
  - CSRF защита,
  - проверка ролей (`EnsureUserHasRole`) и другие ограничения доступа.
- **Controllers**:
  - контроллеры сотрудника (Employee),
  - контроллеры руководителя (Manager),
  - Auth/Account (логин, приглашения, аккаунт).
- **Service layer**:
  - `RequestWorkflowService` (бизнес-логика заявок и переносов),
  - `AuditLogger` (фиксация действий в аудите).
- **Eloquent Models**:
  - `User`, `TempRequest`, `Request`,
  - `Invitation`, `AuditLog`, `Notification`, `UserAddress`.

**Хранилища:**

- Models → **MySQL**: SQL операции; структура формируется миграциями.
- Models → **Storage** (пунктир/пометка): сессии, логи, экспорты.
- Controllers → **Views (Blade)**: рендер страниц `resources/views/...` + подключение темы/стилей.

**Scheduler:** блок показывает, что консольные задачи (например `taxi:cleanup`) определяются в `routes/console.php` и запускаются через `artisan` по расписанию.

## 4) `architecture_frontend.drawio` (Frontend)

**Назначение:** описать клиентскую часть именно этого проекта: Blade-страницы, стили, сборку ассетов и минимальный JS.

**Что в UI:**

- **Pages (Blade)**: страницы лендинга/кабинетов сотрудника и руководителя, аудит, управление сотрудниками/руководителями.
- **UI components**: общие layout’ы, таблицы, формы, бейджи статусов, модальные окна/попапы.

**Стили и JS:**

- **Tailwind (build)** + кастомная тема CSS (например `public/theme-vtb.css`).
- **JS behavior (lightweight)**: небольшие интерактивности (переключатели, улучшения UX форм, мобильные правки). Формы используют CSRF токен.

**Сборка:**

- **Vite + laravel-vite-plugin + Tailwind CSS**: сборка фронтенда.
- **Output assets**: `public/build/manifest.json` и “хэшированные” CSS/JS для продакшна.
- **Browser runtime**: адаптивная верстка + cookies/sessions (важно для авторизации).

## 5) `architecture_frameworks.drawio` (Frameworks / Tech Map)

**Назначение:** “карта стека” — какие технологии есть в проекте и как они связаны.

Схема подчеркивает связи:

- **Laravel 13**: роутинг, Blade, Eloquent, миграции, Scheduler.
- **PHP 8.3**: runtime (CLI и CGI/FPM).
- **MySQL**: реляционное хранилище.
- **nginx/Apache + FastCGI**: веб-серверный слой исполнения PHP.
- **Vite**: сборка.
- **Tailwind CSS v4**: стили.
- **Blade templates**: server-side UI.

## 6) `architecture.drawio` (Общая схема без декомпозиции)

**Назначение:** единая “сводная” схема, которая объединяет основные элементы из server/backend/scheduler.

Содержит те же ключевые узлы:

- Пользователь (браузер) → DNS → TLS/SSL → Web server → PHP 8.3 → Laravel App.
- Laravel App работает с:
  - **MySQL** (заявки/пользователи/аудит),
  - **storage/** (логи, сессии, экспорты).
- **Cron** запускает `artisan schedule:run`, а Laravel Scheduler выполняет `taxi:cleanup` по расписанию.

