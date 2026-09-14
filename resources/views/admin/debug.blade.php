<x-layouts.portal-vtb
    title="Отладка"
    heading="Панель отладки"
    subheading="Генерация тестовых данных. Доступно только администратору."
    :current-user="$currentUser"
>
    <div class="ops-page">

    @if (session('status'))
        <div class="callout callout-info" style="margin-bottom:1rem;">{{ session('status') }}</div>
    @endif

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Состояние БД</span>
                <h2>Текущие счётчики</h2>
            </div>
        </div>
        <div class="ops-kpi-strip">
            <span class="ops-badge">Сотрудников: {{ $employeeCount }}</span>
            <span class="ops-badge ops-badge--pending">Заявок в буфере: {{ $tempRequestCount }}</span>
        </div>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Генерация</span>
                <h2>Создать тестовых сотрудников</h2>
                <p>Случайные ФИО, табельный номер и телефон. Пароль: <code>password</code>.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.debug.generate-employees') }}" class="form-grid">
            @csrf
            <div class="field">
                <label for="emp_count">Количество сотрудников</label>
                <input id="emp_count" class="input" type="number" name="count" value="5" min="1" max="50">
            </div>
            <div class="form-actions">
                <button class="button" type="submit">Создать сотрудников</button>
            </div>
        </form>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Генерация</span>
                <h2>Создать тестовые заявки</h2>
                <p>Заявки будут созданы для выбранных сотрудников на указанные даты и время.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.debug.generate-requests') }}" class="form-grid">
            @csrf

            <div class="field">
                <label class="field-label">Сотрудники</label>
                <div style="display:flex;flex-direction:column;gap:0.35rem;max-height:240px;overflow-y:auto;border:1px solid var(--color-border,#e2e8f0);border-radius:6px;padding:0.5rem;">
                    @forelse ($employees as $emp)
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                            <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}">
                            <span>{{ $emp->full_name }} <span class="muted">({{ $emp->employee_number }})</span></span>
                        </label>
                    @empty
                        <span class="muted">Нет сотрудников. Сначала создайте их выше.</span>
                    @endforelse
                </div>
                @error('employee_ids')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid-two">
                <div class="field">
                    <label for="req_dates">Даты через запятую (дд.мм.гггг)</label>
                    <input id="req_dates" class="input" type="text" name="dates"
                        placeholder="13.05.2026, 14.05.2026, 15.05.2026"
                        value="{{ old('dates') }}">
                    @error('dates')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <small class="muted" style="margin-top:-0.5rem;">Формат: дд.мм.гггг, несколько через запятую.</small>

                <div class="field">
                    <label for="req_time">Время</label>
                    <input id="req_time" class="input" type="time" name="time" value="{{ old('time', '23:00') }}">
                    @error('time')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="req_status">Статус заявок</label>
                <select id="req_status" class="select" name="status">
                    <option value="pending">На согласовании (Pending)</option>
                    <option value="approved">Одобрено (Approved)</option>
                    <option value="rejected">Отклонено (Rejected)</option>
                    <option value="cancelled">Отменено (Cancelled)</option>
                </select>
            </div>

            <div class="form-actions">
                <button class="button" type="submit">Создать заявки</button>
            </div>
        </form>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Очистка</span>
                <h2>Удалить все заявки из буфера</h2>
                <p>Удаляет все записи из таблицы <code>temp_requests</code> безвозвратно.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.debug.clear-requests') }}"
              data-confirm-delete="Удалить ВСЕ заявки из буфера? Это действие нельзя отменить.">
            @csrf
            <div class="form-actions">
                <button class="button-danger" type="submit">Очистить буфер заявок</button>
            </div>
        </form>
    </section>

    </div>
</x-layouts.portal-vtb>
