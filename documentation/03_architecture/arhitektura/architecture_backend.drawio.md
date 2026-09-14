# Легенда: architecture_backend.drawio

Файл: `_about/architecture_backend.drawio` — схема “как устроен backend в коде”.

## Элементы схемы

- **HTTP request**: входящий запрос (GET/POST) от браузера.
- **Routes**: таблица маршрутов Laravel (`routes/web.php`, `routes/api.php`).
- **Middleware**: прослойки до контроллера (права/сессия/CSRF и т.п.).
- **Controllers**: обработчики запросов (Employee/Manager/Admin/Auth).
- **Service layer**: “доменные” сервисы, где сосредоточена бизнес-логика (`RequestWorkflowService`, `AuditLogger`).
- **Eloquent Models**: модели БД (User/TempRequest/Request/Invitation/AuditLog/…).
- **MySQL**: БД.
- **Storage**: файловая система (логи/сессии/экспорт).
- **Views (Blade)**: HTML-шаблоны, которые возвращаются пользователю.
- **Scheduler**: запланированные задачи (например ежедневная очистка).

## Термины

- **Route (маршрут)**: сопоставление URL+метод → контроллер.
- **Middleware**: код, который выполняется “до/после” контроллера.
  - **CSRF**: защита от подделки межсайтовых запросов (в Laravel токен в форме).
  - **Session**: механизм хранения состояния входа.
  - **Auth**: аутентификация.
- **Controller**: класс, который принимает запрос и возвращает ответ.
- **Service layer**: слой бизнес-логики (удобно для тестирования и чтобы контроллеры были тонкими).
- **Eloquent**: ORM Laravel для работы с БД как с объектами.
- **Migration**: описание схемы БД в коде (`database/migrations/...`).
- **Storage disk**: абстракция Laravel для файловых операций.
- **Scheduler**: планировщик Laravel (запускается через `schedule:run`).

## Как читать стрелки

- `HTTP → Routes → Middleware → Controllers` — обработка запроса.
- `Controllers → Service layer → Models → DB/Storage` — бизнес-операции.
- `Controllers → Views` — рендер HTML-страниц.
- `Scheduler → Service layer` — фоновое обслуживание.

