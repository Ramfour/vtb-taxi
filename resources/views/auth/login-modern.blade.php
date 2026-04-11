<x-layouts.portal-vtb
    title="Вход в систему"
    heading="Вход по табельному номеру"
    subheading="Используйте табельный номер VTB и пароль. Если вас только что пригласили в систему, сначала откройте персональную ссылку регистрации."
>
    <section class="auth-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Авторизация</span>
                    <h2>Быстрый доступ в рабочую панель</h2>
                    <p>После входа система сама отправит вас в кабинет сотрудника или в панель руководителя.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="employee_number">Табельный номер</label>
                    <input id="employee_number" class="input" type="text" name="employee_number" value="{{ old('employee_number') }}" placeholder="70320699" required autofocus>
                    <small>Например: 70320699</small>
                </div>

                <div class="field">
                    <label for="password">Пароль</label>
                    <input id="password" class="input" type="password" name="password" required>
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Войти</button>
                    <a href="{{ route('home') }}" class="button-secondary">На главную</a>
                </div>
            </form>
        </article>

        <aside class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Как устроено</span>
                    <h2>Логика входа</h2>
                </div>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Руководитель и сотрудник живут в одном аккаунте</strong>
                    <p>Если у пользователя роль руководителя, он просто получает доступ к обоим разделам интерфейса.</p>
                </div>
                <div class="feature-item">
                    <strong>Самостоятельной открытой регистрации нет</strong>
                    <p>Новый профиль создаётся только по приглашению руководителя, что снижает риск ошибок и лишнего доступа.</p>
                </div>
                <div class="feature-item">
                    <strong>Основа готова под Telegram</strong>
                    <p>Позже можно привязать Telegram как дополнительный канал, не ломая текущую схему авторизации.</p>
                </div>
            </div>
        </aside>
    </section>
</x-layouts.portal-vtb>
