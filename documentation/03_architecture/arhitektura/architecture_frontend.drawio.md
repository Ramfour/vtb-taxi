# Легенда: architecture_frontend.drawio

Файл: `_about/architecture_frontend.drawio` — схема “как устроен фронтенд (UI)”.

## Элементы схемы

- **Pages (Blade)**: страницы в `resources/views/...` (лендинг, кабинет сотрудника, кабинет руководителя, аудит, сотрудники).
- **UI components**: переиспользуемые компоненты/лейауты/части страниц (таблицы, формы, модалки).
- **Styles**:
  - Tailwind (через сборку),
  - `public/theme-vtb.css` (кастомные стили проекта).
- **JS behavior**: небольшой JavaScript для UX (переключатели, мелкие интерактивности), без SPA-фреймворка.
- **Build pipeline**: Vite + Tailwind + laravel-vite-plugin.
- **Output assets**: `public/build/manifest.json` и скомпилированные CSS/JS (для продакшна).
- **Browser runtime**: то, как всё исполняется/рендерится у пользователя (CSS responsive, cookies/sessions).

## Термины

- **Frontend**: пользовательский интерфейс (HTML/CSS/JS) в браузере.
- **Blade**: шаблонизатор Laravel для HTML.
- **Component**: кусок UI, который можно переиспользовать.
- **Tailwind CSS**: утилитарный CSS-фреймворк (классы вида `p-4`, `grid`, `text-sm` и т.п.).
- **Vite**: сборщик фронтенда (dev server и production build).
- **manifest.json**: карта ассетов для Laravel/Vite в проде (если нет — будет ошибка “Vite manifest not found”).
- **Responsive**: адаптивная верстка под разные экраны.
- **Cookie**: данные, которые браузер хранит и отправляет сайту (например для сессии).
- **CSRF token**: токен в формах, чтобы Laravel принимал POST-запросы.

## Практика для продакшна

- После обновления фронтенда важно иметь собранные ассеты (`public/build/...`) или настроенный способ сборки на сервере.

