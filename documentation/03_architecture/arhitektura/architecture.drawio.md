# Легенда: architecture.drawio

Файл: `_about/architecture.drawio` — базовая общая схема архитектуры (первый вариант).

Сейчас дополнительно есть более подробные схемы:

- `_about/architecture_server.drawio` — сервер/хостинг,
- `_about/architecture_backend.drawio` — backend,
- `_about/architecture_frontend.drawio` — frontend,
- `_about/architecture_overview.drawio` — общий вид без декомпозиции,
- `_about/architecture_frameworks.drawio` — карта стека.

## Термины (кратко)

- **DNS**: связывает домен и IP.
- **SSL/TLS**: шифрование (сертификат Let’s Encrypt).
- **nginx**: веб-сервер.
- **FastCGI / PHP-FPM**: связка веб-сервера и PHP.
- **Laravel**: backend-приложение.
- **MySQL**: база данных.
- **storage/**: файлы runtime (логи/сессии/экспорт).
- **Cron**: внешний планировщик.
- **Scheduler**: планировщик Laravel (работает через `schedule:run`).

Если нужно, можно считать эту схему “исторической”, а пользоваться `architecture_overview.drawio` как основной общей.

