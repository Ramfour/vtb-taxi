@php
    $countLabel = function (int $count, string $label): string {
        return $count.' '.$label;
    };
    $roleLabels = [
        'Employee' => 'Сотрудник',
        'Manager' => 'Руководитель',
        'Admin' => 'Администратор',
    ];
    $displayName = function (?string $fullName): string {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) === 3 && preg_match('/(ич|вич|ьмич|оглы|кызы|овна|евна|ична)$/u', $parts[1])) {
            return implode(' ', [$parts[2], $parts[0], $parts[1]]);
        }

        return $fullName;
    };
    $displayAddress = function (?string $address): string {
        $address = trim((string) $address);

        if ($address === '') {
            return '';
        }

        $address = preg_replace('/^Россия,\s*/u', '', $address);
        $address = preg_replace('/\bг\.\s*/u', '', $address);

        return preg_replace('/\s{2,}/u', ' ', $address) ?? $address;
    };
@endphp

<x-layouts.portal-vtb
    title="Сотрудники"
    heading="Сотрудники"
    subheading="Приглашения, текущие сотрудники и управление доступами."
    :current-user="$currentUser"
>
    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Быстрые действия</span>
                <h2>Новый сотрудник</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('manager.invitations.store') }}" class="form-grid">
            @csrf
            <div class="form-grid-two">
                <div class="field">
                    <label for="employee_number">Табельный номер</label>
                    <input id="employee_number" class="input" type="text" name="employee_number" placeholder="70320699" required>
                </div>
                <div class="field">
                    <label for="expires_in_days">Дней активности</label>
                    <input id="expires_in_days" class="input" type="number" name="expires_in_days" value="7" min="1" max="30">
                </div>
            </div>
            @if ($currentUser?->role?->name === 'Admin')
                <div class="field">
                    <label for="invitation_role">Роль приглашения</label>
                    <select id="invitation_role" class="select" name="role">
                        <option value="employee">Сотрудник</option>
                        <option value="manager">Руководитель</option>
                    </select>
                </div>
            @endif

            <div class="form-actions">
                <button class="button" type="submit">Создать приглашение</button>
            </div>
        </form>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Текущие сотрудники</span>
                <h2>Список сотрудников</h2>
            </div>
            <span class="pill-count">{{ $countLabel($employees->total(), 'чел.') }}</span>
        </div>

        <form method="GET" action="{{ route('manager.employees.index') }}" class="form-grid" style="margin-bottom: 16px;">
            <div class="form-grid-two">
                <div class="field">
                    <label for="employee_search">Поиск по ФИО, табельному или телефону</label>
                    <input id="employee_search" class="input" type="text" name="q" value="{{ $search }}" placeholder="Например: 70320699 или Смирнов">
                </div>
                <div class="form-actions" style="align-self: end;">
                    <button class="button" type="submit">Найти</button>
                    <a class="button-ghost" href="{{ route('manager.employees.index') }}">Сбросить</a>
                </div>
            </div>
        </form>

        <div class="employee-table">
            <div class="employee-table__head">
                <span>Сотрудник</span>
                <span>Роль</span>
                <span>Табельный</span>
                <span>Телефон</span>
                <span>Адрес</span>
                <span>Действия</span>
            </div>

            @forelse ($employees as $employee)
                <div class="employee-row">
                    <div class="employee-name" data-label="Сотрудник">{{ $displayName($employee->full_name) }}</div>
                    <div data-label="Роль">{{ $roleLabels[$employee->role->name] ?? $employee->role->name }}</div>
                    <div data-label="Табельный">{{ $employee->employee_number }}</div>
                    <div data-label="Телефон">{{ $employee->phone }}</div>
                    <div class="employee-address" data-label="Адрес">{{ $displayAddress($employee->latestAddress?->address) }}</div>
                    <div class="employee-actions" data-label="Действия">
                        <form method="POST" action="{{ route('manager.users.destroy', $employee) }}" data-confirm-delete="Удалить сотрудника из системы? Все его заявки останутся в истории.">
                            @csrf
                            @method('DELETE')
                            <button class="button-danger" type="submit">Удалить</button>
                        </form>
                        @if ($currentUser?->role?->name === 'Admin')
                            <form method="POST" action="{{ route('manager.users.destroy-force', $employee) }}" data-confirm-delete="Удалить сотрудника навсегда? Это действие нельзя отменить.">
                                @csrf
                                @method('DELETE')
                                <button class="button-danger" type="submit">Удалить навсегда</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    Сотрудников пока нет.
                </div>
            @endforelse
        </div>

        <div class="pagination">
            <span class="muted">Страница {{ $employees->currentPage() }} из {{ $employees->lastPage() }}</span>
            <div class="pagination__actions">
                @if ($employees->onFirstPage())
                    <span class="button-ghost">Назад</span>
                @else
                    <a class="button-ghost" href="{{ $employees->previousPageUrl() }}">Назад</a>
                @endif

                @if ($employees->hasMorePages())
                    <a class="button-ghost" href="{{ $employees->nextPageUrl() }}">Вперёд</a>
                @else
                    <span class="button-ghost">Вперёд</span>
                @endif
            </div>
        </div>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Последние приглашения</span>
                <h2>Ссылки на регистрацию</h2>
            </div>
        </div>

        <div class="list-stack">
            @forelse ($invitations as $invitation)
                <article class="invitation-card invitation-card--compact">
                    <strong>{{ $invitation->employee_number }}</strong>
                    <p>{{ $invitation->is_used ? 'Ссылка уже использована' : 'Ожидает активации' }} · до {{ optional($invitation->expires_at)->format('d.m.Y H:i') }}</p>

                    <div class="copy-box">
                        <code id="invite-link-{{ $invitation->id }}">{{ route('invitation.accept.show', $invitation->token) }}</code>
                        <button type="button" class="button-secondary copy-button" data-copy-target="#invite-link-{{ $invitation->id }}">Копировать</button>
                        <button type="button" class="button-ghost" data-qr-link="{{ route('invitation.accept.show', $invitation->token) }}">QR</button>
                        <form method="POST" action="{{ route('manager.invitations.destroy', $invitation) }}" data-confirm-delete="Удалить приглашение? Ссылка перестанет работать." class="inline-form">
                            @csrf
                            @method('DELETE')
                            <button class="button-danger" type="submit">Удалить</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    Приглашений пока нет.
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.portal-vtb>
