@php
    $employeeCount = $employeeCount ?? 0;
    $managerCount = $managerCount ?? 0;
@endphp

<x-layouts.portal-vtb
    title="VTB Taxi"
    heading="Цифровой контур заявок на корпоративное такси"
    subheading="Система для ВТБ, в которой сотрудник отправляет заявку за минуты, а руководитель согласовывает поток без таблиц, хаоса и ручной пересборки."
>
    <section class="hero-grid">
        <article class="hero-banner" data-reveal>
            <span class="kicker">Корпоративный сервис</span>
            <h2>Один интерфейс для сотрудника, руководителя и финальной диспетчеризации.</h2>
            <p>
                Уже работает вход по табельному номеру, приглашения для новых сотрудников, буфер согласования,
                кабинет руководителя и жизненный цикл заявки от подачи до финализации.
            </p>

            <div class="metric-grid">
                <div class="stat-card">
                    <span class="stat-label">Сотрудники</span>
                    <div class="stat-value">{{ $employeeCount }}</div>
                    <div class="stat-note">Активные пользователи, оформляющие заявки.</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Руководители</span>
                    <div class="stat-value">{{ $managerCount }}</div>
                    <div class="stat-note">Согласуют заявки и управляют доступом.</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Выгрузка</span>
                    <div class="stat-value">CSV</div>
                    <div class="stat-note">Финальные заявки выгружаются для перевозчика.</div>
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
                    <span class="kicker">Доступ</span>
                    <h2>Как получить вход в систему</h2>
                    <p>Вход выполняется по табельному номеру и паролю. Если доступа ещё нет — руководитель отправит приглашение.</p>
                </div>
            </div>

            <div class="plain-list">
                <div class="timeline-card">
                    <strong>Приглашение от руководителя</strong>
                    <p>Руководитель создаёт одноразовую ссылку, сотрудник завершает регистрацию и задаёт пароль.</p>
                </div>
                <div class="timeline-card">
                    <strong>Вход по табельному номеру</strong>
                    <p>После регистрации используйте табельный номер VTB и пароль для авторизации.</p>
                </div>
            </div>
        </article>
    </section>
</x-layouts.portal-vtb>
