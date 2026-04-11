@php
    $buffer = $dashboard['buffer'];
    $finalized = $dashboard['finalized'];
    $pendingCount = $buffer->where('status', \App\Enums\RequestStatus::Pending)->count();
    $approvedCount = $buffer->where('status', \App\Enums\RequestStatus::Approved)->count();
    $countLabel = function (int $count, string $label): string {
        return $count.' '.$label;
    };
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
    title="Панель руководителя"
    heading="Панель руководителя"
    subheading="Согласование заявок, приглашения сотрудников и перевод подтверждённых поездок в финальный слой для выгрузки."
    :current-user="$currentUser"
>
    <section class="manager-grid manager-grid--compact">
        <article class="panel panel--compact" data-reveal>
            <div class="panel-header panel-header--tight">
                <div>
                    <span class="kicker">Сводка</span>
                    <h2>Рабочий статус смены</h2>
                    <p>Ключевые числа наверху, всё остальное ниже без визуального шума.</p>
                </div>
            </div>

            <div class="metric-grid metric-grid--dense">
                <div class="stat-card">
                    <span class="stat-label">Всего в буфере</span>
                    <div class="stat-value">{{ $buffer->count() }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">На согласовании</span>
                    <div class="stat-value">{{ $pendingCount }}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Готовы к переносу</span>
                    <div class="stat-value">{{ $approvedCount }}</div>
                </div>
            </div>
        </article>

        <aside class="compact-side-stack">
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

                    <div class="form-actions">
                        <button class="button" type="submit">Создать приглашение</button>
                    </div>
                </form>
            </section>

            <details class="compact-disclosure panel panel--compact" data-reveal>
                <summary>
                    <span>
                        <span class="kicker">Сворачиваемый раздел</span>
                        <strong>Перенос одобренных заявок</strong>
                    </span>
                    <span class="disclosure-meta">
                        <span class="disclosure-hint">Нажмите, чтобы раскрыть</span>
                        <span class="pill-count">{{ $countLabel($approvedCount, 'к переносу') }}</span>
                    </span>
                </summary>

                <div class="disclosure-body">
                    <p class="muted">Когда вы запускаете перенос, система берёт уже одобренные заявки из временного буфера и делает из них финальные записи. После этого они считаются подтверждёнными для выгрузки и попадают в основной рабочий список.</p>

                    <form method="POST" action="{{ route('manager.requests.finalize') }}" class="form-grid">
                        @csrf
                        <div class="form-grid-two">
                            <div class="field">
                                <label for="from">С</label>
                                <input id="from" class="input" type="datetime-local" name="from">
                            </div>
                            <div class="field">
                                <label for="to">По</label>
                                <input id="to" class="input" type="datetime-local" name="to">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="button-warm" type="submit">Перенести в финальный список</button>
                        </div>
                    </form>
                </div>
            </details>
        </aside>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Буфер согласования</span>
                <h2>Очередь заявок</h2>
                <p>Основной рабочий блок сделан плотнее и ближе по ощущению к табличному инструменту.</p>
            </div>
                <span class="pill-count">{{ $countLabel($buffer->count(), 'в очереди') }}</span>
        </div>

        <form id="bulk-approve-form" method="POST" action="{{ route('manager.requests.bulk-approve') }}" class="buffer-toolbar">
            @csrf
            <div class="field buffer-toolbar__comment">
                <label for="bulk_manager_comment">Комментарий руководителя для массового одобрения</label>
                <textarea id="bulk_manager_comment" class="textarea textarea--compact" name="manager_comment" placeholder="Необязательно. Комментарий будет записан во все массово одобренные заявки."></textarea>
            </div>

            <div class="buffer-toolbar__actions">
                <button class="button-success" type="submit" name="approve_scope" value="selected">Одобрить выбранные</button>
                <button class="button-secondary" type="submit" name="approve_scope" value="all_pending">Одобрить все на согласовании</button>
            </div>
        </form>

        <div class="request-table">
            <div class="request-table__head">
                <span>Выбор</span>
                <span>Сотрудник</span>
                <span>Когда</span>
                <span>Телефон</span>
                <span>Адрес подачи</span>
                <span>Статус</span>
                <span>Действия</span>
            </div>

            @forelse ($buffer as $request)
                <div class="request-row">
                    <div class="request-row__select">
                        @if ($request->status === \App\Enums\RequestStatus::Pending)
                            <input type="checkbox" name="request_ids[]" value="{{ $request->id }}" form="bulk-approve-form" aria-label="Выбрать заявку {{ $request->id }}">
                        @endif
                    </div>

                    <div class="request-row__employee">
                        <strong>{{ $displayName($request->full_name) }}</strong>
                        <span>{{ $request->user?->employee_number }}</span>
                    </div>

                    <div class="request-row__date">{{ $request->date_time?->format('d.m.Y H:i') }}</div>
                    <div class="request-row__phone">{{ $request->phone }}</div>
                    <div class="request-row__address">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>

                    <div class="request-row__status">
                        <x-status-pill :status="$request->status" />
                    </div>

                    <div class="request-row__actions">
                        @if ($request->status === \App\Enums\RequestStatus::Pending)
                            <details class="row-actions">
                                <summary>Открыть действия</summary>

                                <div class="row-actions__body">
                                    <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="row-actions__form">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="approve">
                                        <label for="manager_comment_{{ $request->id }}">Комментарий руководителя</label>
                                        <textarea id="manager_comment_{{ $request->id }}" class="textarea textarea--compact" name="manager_comment" placeholder="Необязательно"></textarea>
                                        <button class="button-success" type="submit">Одобрить</button>
                                    </form>

                                    <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="row-actions__form">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="reject">
                                        <label for="rejection_reason_{{ $request->id }}">Причина отклонения</label>
                                        <textarea id="rejection_reason_{{ $request->id }}" class="textarea textarea--compact" name="rejection_reason" required placeholder="Например: требуется уточнение адреса"></textarea>
                                        <button class="button-danger" type="submit">Отклонить</button>
                                    </form>
                                </div>
                            </details>
                        @else
                            <span class="muted">Без действий</span>
                        @endif
                    </div>

                    @if ($request->manager_comment || $request->rejection_reason)
                        <div class="request-row__notes">
                            @if ($request->manager_comment)
                                <div class="callout callout-info">{{ $request->manager_comment }}</div>
                            @endif
                            @if ($request->rejection_reason)
                                <div class="callout callout-danger">{{ $request->rejection_reason }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    Буфер пуст. Как только сотрудники отправят новые поездки, они появятся здесь.
                </div>
            @endforelse
        </div>
    </section>

    <section class="manager-grid manager-grid--compact">
        <details class="compact-disclosure panel panel--compact" data-reveal>
            <summary>
                <span>
                    <span class="kicker">Сворачиваемый раздел</span>
                    <strong>Последние приглашения</strong>
                </span>
                <span class="disclosure-meta">
                    <span class="disclosure-hint">Нажмите, чтобы раскрыть</span>
                    <span class="pill-count">{{ $countLabel($invitations->count(), 'ссылок') }}</span>
                </span>
            </summary>

            <div class="disclosure-body">
                <div class="list-stack">
                    @forelse ($invitations as $invitation)
                        <article class="invitation-card invitation-card--compact">
                            <strong>{{ $invitation->employee_number }}</strong>
                            <p>{{ $invitation->is_used ? 'Ссылка уже использована' : 'Ожидает активации' }} · до {{ optional($invitation->expires_at)->format('d.m.Y H:i') }}</p>

                            <div class="copy-box">
                                <code id="invite-link-{{ $invitation->id }}">{{ route('invitation.accept.show', $invitation->token) }}</code>
                                <button type="button" class="button-secondary copy-button" data-copy-target="#invite-link-{{ $invitation->id }}">Копировать</button>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">
                            Приглашений пока нет.
                        </div>
                    @endforelse
                </div>
            </div>
        </details>

        <details class="compact-disclosure panel panel--compact" data-reveal>
            <summary>
                <span>
                    <span class="kicker">Сворачиваемый раздел</span>
                    <strong>Недавние финальные заявки</strong>
                </span>
                <span class="disclosure-meta">
                    <span class="disclosure-hint">Нажмите, чтобы раскрыть</span>
                    <span class="pill-count">{{ $countLabel($finalized->count(), 'записей') }}</span>
                </span>
            </summary>

            <div class="disclosure-body">
                <div class="list-stack">
                    @forelse ($finalized as $request)
                        <article class="invitation-card invitation-card--compact">
                            <strong>{{ $displayName($request->full_name) }}</strong>
                            <p>{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</p>
                            <div class="list-address">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>
                        </article>
                    @empty
                        <div class="empty-state">
                            Финальных записей пока нет.
                        </div>
                    @endforelse
                </div>
            </div>
        </details>
    </section>
</x-layouts.portal-vtb>
