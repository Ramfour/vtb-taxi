@php
    $buffer = $dashboard['buffer'];
    $finalized = $dashboard['finalized'];
    $pendingCount = $buffer->where('status', \App\Enums\RequestStatus::Pending)->count();
    $approvedCount = $buffer->where('status', \App\Enums\RequestStatus::Approved)->count();
@endphp

<x-layouts.portal-vtb
    title="Панель руководителя"
    heading="Панель руководителя"
    subheading="Управляйте входящим буфером заявок, запускайте регистрацию сотрудников и готовьте финальный слой для выгрузки перевозчику."
    :current-user="$currentUser"
>
    <section class="manager-grid">
        <article class="hero-banner" data-reveal>
            <span class="kicker">Контроль потока</span>
            <h2>В одном окне: согласование, приглашения и финализация.</h2>
            <p>Руководитель может подавать собственные поездки как сотрудник и здесь же управлять всей очередью команды.</p>

            <div class="metric-grid">
                <div class="stat-card">
                    <span class="stat-label">Всего в буфере</span>
                    <div class="stat-value">{{ $buffer->count() }}</div>
                    <div class="stat-note">Все записи из `temp_requests`.</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Ожидают решения</span>
                    <div class="stat-value">{{ $pendingCount }}</div>
                    <div class="stat-note">Их ещё нужно одобрить или отклонить.</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Готовы к переносу</span>
                    <div class="stat-value">{{ $approvedCount }}</div>
                    <div class="stat-note">Можно переносить в основной слой.</div>
                </div>
            </div>
        </article>

        <aside class="page-stack">
            <section class="panel" data-reveal>
                <div class="panel-header">
                    <div>
                        <span class="kicker">Приглашения</span>
                        <h2>Завести нового сотрудника</h2>
                        <p>Введите табельный номер, и система выдаст ссылку для завершения регистрации.</p>
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
                            <label for="expires_in_days">Срок действия, дней</label>
                            <input id="expires_in_days" class="input" type="number" name="expires_in_days" value="7" min="1" max="30">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="button" type="submit">Создать приглашение</button>
                    </div>
                </form>
            </section>

            <section class="panel" data-reveal>
                <div class="panel-header">
                    <div>
                        <span class="kicker">Финализация</span>
                        <h2>Перенос одобренных заявок</h2>
                        <p>Перенос переводит одобренные записи из временного буфера в основную таблицу `requests` для дальнейшей выгрузки.</p>
                    </div>
                </div>

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
                        <button class="button-warm" type="submit">Перенести в requests</button>
                    </div>
                </form>
            </section>
        </aside>
    </section>

    <section class="manager-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Буфер согласования</span>
                    <h2>Входящие заявки сотрудников</h2>
                    <p>Можно одобрять по одной, массово по выбранным заявкам или сразу весь текущий pending-пул.</p>
                </div>
                <span class="pill-count">{{ $buffer->count() }}</span>
            </div>

            <form id="bulk-approve-form" method="POST" action="{{ route('manager.requests.bulk-approve') }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="bulk_manager_comment">Комментарий руководителя для массового одобрения</label>
                    <textarea id="bulk_manager_comment" class="textarea" name="manager_comment" placeholder="Необязательно. Комментарий будет записан во все одобренные выбранные заявки."></textarea>
                </div>

                <div class="form-actions">
                    <button class="button-success" type="submit" name="approve_scope" value="selected">Одобрить выбранные</button>
                    <button class="button-secondary" type="submit" name="approve_scope" value="all_pending">Одобрить все pending</button>
                </div>
            </form>

            <div class="list-stack">
                @forelse ($buffer as $request)
                    <article class="list-card">
                        <div class="list-head">
                            <div>
                                <strong>{{ $request->full_name }}</strong>
                                <div class="list-meta">{{ $request->user?->employee_number }} · {{ $request->phone }} · {{ $request->date_time?->format('d.m.Y H:i') }}</div>
                            </div>
                            <div class="manager-request-actions">
                                @if ($request->status === \App\Enums\RequestStatus::Pending)
                                    <label class="select-request">
                                        <input type="checkbox" name="request_ids[]" value="{{ $request->id }}" form="bulk-approve-form">
                                        <span>Выбрать</span>
                                    </label>
                                @endif
                                <x-status-pill :status="$request->status" />
                            </div>
                        </div>

                        <div class="list-address">{{ $request->address_norm ?? $request->address_raw }}</div>

                        @if ($request->status === \App\Enums\RequestStatus::Pending)
                            <div class="cluster-grid" style="margin-top: 16px;">
                                <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="timeline-card">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="approve">

                                    <div class="field">
                                        <label for="manager_comment_{{ $request->id }}">Комментарий руководителя</label>
                                        <textarea id="manager_comment_{{ $request->id }}" class="textarea" name="manager_comment" placeholder="Необязательно, но можно пояснить решение"></textarea>
                                    </div>

                                    <div class="form-actions">
                                        <button class="button-success" type="submit">Одобрить</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="timeline-card">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="reject">

                                    <div class="field">
                                        <label for="rejection_reason_{{ $request->id }}">Причина отклонения</label>
                                        <textarea id="rejection_reason_{{ $request->id }}" class="textarea" name="rejection_reason" required placeholder="Например: время не согласовано или адрес требует уточнения"></textarea>
                                    </div>

                                    <div class="form-actions">
                                        <button class="button-danger" type="submit">Отклонить</button>
                                    </div>
                                </form>
                            </div>
                        @else
                            @if ($request->manager_comment)
                                <div class="callout callout-info">{{ $request->manager_comment }}</div>
                            @endif
                            @if ($request->rejection_reason)
                                <div class="callout callout-danger">{{ $request->rejection_reason }}</div>
                            @endif
                        @endif
                    </article>
                @empty
                    <div class="empty-state">
                        Буфер пуст. Как только сотрудники отправят новые поездки, они появятся здесь.
                    </div>
                @endforelse
            </div>
        </article>

        <aside class="page-stack">
            <section class="panel" data-reveal>
                <div class="panel-header">
                    <div>
                        <span class="kicker">Последние приглашения</span>
                        <h2>Ссылки для регистрации</h2>
                        <p>Их можно сразу отправлять сотрудникам в мессенджере или по почте.</p>
                    </div>
                    <span class="pill-count">{{ $invitations->count() }}</span>
                </div>

                <div class="list-stack">
                    @forelse ($invitations as $invitation)
                        <article class="invitation-card">
                            <strong>{{ $invitation->employee_number }}</strong>
                            <p>{{ $invitation->is_used ? 'Ссылка уже использована' : 'Ожидает активации' }} · до {{ optional($invitation->expires_at)->format('d.m.Y H:i') }}</p>

                            <div class="copy-box">
                                <code id="invite-link-{{ $invitation->id }}">{{ route('invitation.accept.show', $invitation->token) }}</code>
                                <button type="button" class="button-secondary copy-button" data-copy-target="#invite-link-{{ $invitation->id }}">Копировать</button>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">
                            Приглашений пока нет. Создайте первое через форму выше.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="panel" data-reveal>
                <div class="panel-header">
                    <div>
                        <span class="kicker">Финальный слой</span>
                        <h2>Недавние перенесённые заявки</h2>
                        <p>Эти записи уже находятся в основной таблице и станут источником будущей CSV-выгрузки.</p>
                    </div>
                    <span class="pill-count">{{ $finalized->count() }}</span>
                </div>

                <div class="list-stack">
                    @forelse ($finalized as $request)
                        <article class="list-card">
                            <div class="list-head">
                                <div>
                                    <strong>{{ $request->full_name }}</strong>
                                    <div class="list-meta">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</div>
                                </div>
                                <x-status-pill :status="$request->status" />
                            </div>

                            <div class="list-address">{{ $request->address_norm ?? $request->address_raw }}</div>
                        </article>
                    @empty
                        <div class="empty-state">
                            Финальных записей пока нет. После переноса одобренных заявок они появятся в этом блоке.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </section>
</x-layouts.portal-vtb>
