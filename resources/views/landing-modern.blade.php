@php
    $employeeCount = $employees->count();
    $managerCount = $managers->count();
@endphp

<x-layouts.portal-vtb
    title="VTB Taxi"
    heading="Цифровой контур заявок на корпоративное такси"
    subheading="Система для ВТБ, в которой сотрудник отправляет заявку за минуты, а руководитель согласовывает поток без таблиц, хаоса и ручной пересборки."
>
    <section class="hero-grid">
        <article class="hero-banner" data-reveal>
            <span class="kicker">Запуск MVP</span>
            <h2>Один интерфейс для сотрудника, руководителя и финальной диспетчеризации.</h2>
            <p>
                Уже работает вход по табельному номеру, приглашения для новых сотрудников, буфер согласования,
                кабинет руководителя и жизненный цикл заявки от подачи до финализации.
            </p>

            <div class="metric-grid">
                <div class="stat-card">
                    <span class="stat-label">Сотрудники в демо</span>
                    <div class="stat-value">{{ $employeeCount }}</div>
                    <div class="stat-note">Готовы для тестовой регистрации и подачи заявок.</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Руководители</span>
                    <div class="stat-value">{{ $managerCount }}</div>
                    <div class="stat-note">Могут согласовывать заявки и создавать приглашения.</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Следующий этап</span>
                    <div class="stat-value">CSV</div>
                    <div class="stat-note">Готовим красивый интерфейс под рабочую выгрузку перевозчику.</div>
                </div>
            </div>

            <div class="hero-actions" style="margin-top: 26px;">
                <a href="{{ route('login') }}" class="button">Войти в систему</a>
                <a href="#flow" class="button-secondary">Посмотреть сценарий</a>
            </div>
        </article>

        <aside class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Что уже есть</span>
                    <h2>Рабочий контур для руководителя</h2>
                </div>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Вход по табельному номеру</strong>
                    <p>Логин строится на VTB employee number, без лишних аккаунтов и дублирования профилей.</p>
                </div>
                <div class="feature-item">
                    <strong>Приглашения сотрудников</strong>
                    <p>Руководитель создаёт одноразовую ссылку, а сотрудник сам завершает регистрацию и задаёт пароль.</p>
                </div>
                <div class="feature-item">
                    <strong>Буфер согласования</strong>
                    <p>Все новые заявки проходят через временный слой, прежде чем попасть в финальную выборку.</p>
                </div>
            </div>
        </aside>
    </section>

    <section id="flow" class="dashboard-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Сценарий</span>
                    <h2>Как движется заявка</h2>
                    <p>Процесс уже разбит на этапы и хорошо масштабируется под будущие интеграции.</p>
                </div>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>1. Руководитель создаёт приглашение</strong>
                    <p>Достаточно табельного номера. Система выпускает ссылку на активацию профиля.</p>
                </div>
                <div class="feature-item">
                    <strong>2. Сотрудник завершает регистрацию</strong>
                    <p>Заполняет ФИО, телефон, адрес по умолчанию и пароль для входа в веб-интерфейс.</p>
                </div>
                <div class="feature-item">
                    <strong>3. Заявка уходит в буфер</strong>
                    <p>Руководитель видит входящий поток, принимает решение и позже переводит заявки в финальный слой.</p>
                </div>
            </div>
        </article>

        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Тестовые доступы</span>
                    <h2>Можно посмотреть прямо сейчас</h2>
                    <p>Для демо уже подготовлены пользователи и пароль по умолчанию.</p>
                </div>
                <span class="pill-count">Пароль: password</span>
            </div>

            <div class="plain-list">
                <div class="timeline-card">
                    <strong>MNG0001</strong>
                    <p>Основной руководитель. Открывает панель согласования и создаёт приглашения.</p>
                </div>
                <div class="timeline-card">
                    <strong>EMP0001</strong>
                    <p>Администратор. Имеет расширенные права и также может работать как руководитель.</p>
                </div>
                <div class="timeline-card">
                    <strong>EMP0002 / EMP0003</strong>
                    <p>Демо-сотрудники. Через их аккаунты удобно проверять подачу новых заявок.</p>
                </div>
            </div>
        </article>
    </section>
</x-layouts.portal-vtb>
