<x-layouts.portal-vtb
    title="Активация приглашения"
    heading="Завершение регистрации"
    subheading="Приглашение создано для табельного номера {{ $invitation->employee_number }}. Заполните профиль один раз, после этого сможете входить в систему по табельному номеру и паролю."
>
    <section class="auth-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Регистрация</span>
                    <h2>Заполните рабочий профиль</h2>
                    <p>Телефон нужен для будущей выгрузки и уведомлений. Адрес по умолчанию можно использовать как шаблон для новых заявок.</p>
                </div>
                <span class="pill-count">{{ $invitation->employee_number }}</span>
            </div>

            <form method="POST" action="{{ route('invitation.accept.store', $invitation->token) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="full_name">ФИО</label>
                    <input id="full_name" class="input" type="text" name="full_name" value="{{ old('full_name') }}" required>
                </div>

                <div class="form-grid-two">
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" class="input" type="text" name="phone" value="{{ old('phone') }}" placeholder="89131234567" required>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" placeholder="optional@vtb.ru">
                    </div>
                </div>

                <div class="field">
                    <label for="default_address">Адрес по умолчанию</label>
                    <textarea id="default_address" class="textarea" name="default_address" placeholder="Например: ул. Ленина, д. 12">{{ old('default_address') }}</textarea>
                </div>

                <div class="form-grid-two">
                    <div class="field">
                        <label for="password">Пароль</label>
                        <div class="password-field">
                            <input id="password" class="input" type="password" name="password" required>
                            <button class="password-toggle" type="button" data-password-toggle>Показать</button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Подтверждение пароля</label>
                        <div class="password-field">
                            <input id="password_confirmation" class="input" type="password" name="password_confirmation" required>
                            <button class="password-toggle" type="button" data-password-toggle>Показать</button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Завершить регистрацию</button>
                </div>
            </form>
        </article>

        <aside class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Что дальше</span>
                    <h2>После активации</h2>
                </div>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Сразу входите в систему</strong>
                    <p>После завершения формы система авторизует вас автоматически и переведёт в личный кабинет.</p>
                </div>
                <div class="feature-item">
                    <strong>Подаёте заявки без повторного ввода</strong>
                    <p>ФИО и контактные данные будут подставляться автоматически в форму новой поездки.</p>
                </div>
                <div class="feature-item">
                    <strong>Подготовка под будущий бот</strong>
                    <p>Позже к аккаунту можно будет добавить Telegram ID и использовать оба канала параллельно.</p>
                </div>
            </div>
        </aside>
    </section>
</x-layouts.portal-vtb>
