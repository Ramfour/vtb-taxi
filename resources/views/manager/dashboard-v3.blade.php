@php
    $buffer = $dashboard['buffer'];
    $finalized = $dashboard['finalized'];
    $bufferItems = $buffer instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? collect($buffer->items())
        : $buffer;
    $bufferTotal = $dashboard['buffer_total'] ?? $bufferItems->count();
    $bufferVisibleTotal = $dashboard['buffer_visible_total'] ?? $bufferItems->count();
    $bufferStatus = $dashboard['buffer_status'] ?? 'all';
    $bufferSort = $dashboard['buffer_sort'] ?? 'asc';
    $pendingCount = $dashboard['pending_total'] ?? $bufferItems->where('status', \App\Enums\RequestStatus::Pending)->count();
    $approvedCount = $dashboard['approved_total'] ?? $bufferItems->where('status', \App\Enums\RequestStatus::Approved)->count();
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
    <div class="ops-page">

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Буфер согласования</span>
                <h2>Очередь заявок</h2>
                <p>Основной рабочий блок сделан плотнее и ближе по ощущению к табличному инструменту.</p>
            </div>
            <span class="pill-count">{{ $countLabel($bufferVisibleTotal, 'в очереди') }}</span>
        </div>

        <div class="buffer-shell" aria-label="Очередь со sticky панелью">
            <div class="buffer-sticky buffer-sticky--outer">
                <div class="ops-kpi-strip" aria-label="Сводка по очереди">
                    <span class="ops-badge">Все: {{ $bufferTotal }}</span>
                    <span class="ops-badge ops-badge--pending">На согласовании: {{ $pendingCount }}</span>
                    <span class="ops-badge ops-badge--approved">Готовы к переносу: {{ $approvedCount }}</span>
                </div>

                <div class="buffer-filters">
                    <form method="GET" action="{{ route('manager.requests.index') }}" class="buffer-filter-group">
                        <input type="hidden" name="buffer_sort" value="{{ $bufferSort }}">
                        <button class="button-ghost {{ $bufferStatus === 'all' ? 'is-active' : '' }}" type="submit" name="buffer_status" value="all">Все заявки</button>
                        <button class="button-ghost {{ $bufferStatus === 'pending' ? 'is-active' : '' }}" type="submit" name="buffer_status" value="pending">Только на согласовании</button>
                    </form>
                    <form method="GET" action="{{ route('manager.requests.index') }}" class="buffer-filter-group">
                        <input type="hidden" name="buffer_status" value="{{ $bufferStatus }}">
                        <button class="button-ghost {{ $bufferSort === 'asc' ? 'is-active' : '' }}" type="submit" name="buffer_sort" value="asc">Сначала ближайшие</button>
                        <button class="button-ghost {{ $bufferSort === 'desc' ? 'is-active' : '' }}" type="submit" name="buffer_sort" value="desc">Сначала дальние</button>
                    </form>
                </div>

                <form id="bulk-approve-form" method="POST" action="{{ route('manager.requests.bulk-approve') }}" class="buffer-toolbar">
                    @csrf
                    <input type="hidden" name="approve_scope" value="selected" data-approve-scope-input>
                    <details class="ops-disclosure ops-disclosure--inline">
                        <summary>Комментарий (необязательно)</summary>
                        <div class="disclosure-body">
                            <div class="field buffer-toolbar__comment">
                                <label for="bulk_manager_comment">Комментарий руководителя для массового одобрения</label>
                                <textarea id="bulk_manager_comment" class="textarea textarea--compact" name="manager_comment" placeholder="Необязательно. Комментарий будет записан во все массово одобренные заявки."></textarea>
                            </div>
                        </div>
                    </details>

                    <div class="buffer-toolbar__actions">
                        <span class="buffer-toolbar__selected muted" aria-live="polite" data-selected-count>Выбрано: 0</span>
                        <button class="button-ghost" type="button" data-clear-selection>Снять выделение</button>
                        <button class="button-success" type="submit" name="approve_scope" value="selected" data-approve-scope="selected">Одобрить выбранные</button>
                        <button class="button-secondary" type="submit" name="approve_scope" value="all_pending" data-approve-scope="all_pending">Одобрить все на согласовании</button>
                    </div>
                </form>

            </div>

            <div class="buffer-scroll">
            <div class="request-table request-table--scroll">
                <div class="request-table__head request-table__head--sticky" role="row">
                    <span>№</span>
                    <span>Выбор</span>
                    <span>Сотрудник</span>
                    <span>Когда</span>
                    <span>Телефон</span>
                    <span>Адрес подачи</span>
                    <span>Статус</span>
                    <span>Действия</span>
                </div>

            @forelse ($bufferItems as $index => $request)
                <div class="request-row">
                    <div class="request-row__index" data-label="№">{{ $index + 1 }}</div>
                <div class="request-row__select" data-label="Выбор">
                    <label class="select-check">
                        @if ($request->status === \App\Enums\RequestStatus::Pending)
                            <input type="checkbox" name="request_ids[]" value="{{ $request->id }}" form="bulk-approve-form" aria-label="Выбрать заявку {{ $request->id }}">
                        @else
                            <input type="checkbox" disabled aria-label="Выбор недоступен">
                        @endif
                        <span class="select-check__box" aria-hidden="true"></span>
                    </label>
                </div>

                    <div class="request-row__employee" data-label="Сотрудник">
                        <strong>{{ $displayName($request->full_name) }}</strong>
                        <span>{{ $request->user?->employee_number }}</span>
                    </div>

                    <div class="request-row__date" data-label="Когда">{{ $request->date_time?->format('d.m.Y H:i') }}</div>
                    <div class="request-row__phone" data-label="Телефон">{{ $request->phone }}</div>
                    <div class="request-row__address" data-label="Адрес подачи">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>

                    <div class="request-row__status" data-label="Статус">
                        <x-status-pill :status="$request->status" />
                    </div>

                    <div class="request-row__actions" data-label="Действия">
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
                                    @if ($currentUser?->role?->name === 'Admin')
                                        <form method="POST" action="{{ route('manager.requests.destroy-temp', $request) }}" class="row-actions__form" data-confirm-delete="Удалить заявку навсегда? Это действие нельзя отменить.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button-danger" type="submit">Удалить запись</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @else
                            @if ($currentUser?->role?->name === 'Admin')
                                <form method="POST" action="{{ route('manager.requests.destroy-temp', $request) }}" class="row-actions__form" data-confirm-delete="Удалить заявку навсегда? Это действие нельзя отменить.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button-danger" type="submit">Удалить запись</button>
                                </form>
                            @else
                                <span class="muted">Без действий</span>
                            @endif
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
            </div>
        </div>

        @if ($buffer instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pagination">
                <span class="muted">Страница {{ $buffer->currentPage() }} из {{ $buffer->lastPage() }}</span>
                <div class="pagination__actions">
                    @if ($buffer->onFirstPage())
                        <span class="button-ghost">Назад</span>
                    @else
                        <a class="button-ghost" href="{{ $buffer->previousPageUrl() }}">Назад</a>
                    @endif

                    @if ($buffer->hasMorePages())
                        <a class="button-ghost" href="{{ $buffer->nextPageUrl() }}">Вперёд</a>
                    @else
                        <span class="button-ghost">Вперёд</span>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section class="panel panel--compact buffer-transfer" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Финализация</span>
                <h2>Перенести заявки в базу перед выгрузкой</h2>
                <p>Выберите период и перенесите одобренные заявки в финальный слой. Предпросмотр ниже показывает, какие записи попадут в выгрузку.</p>
            </div>
            <span class="pill-count">{{ $countLabel($approvedCount, 'к переносу') }}</span>
        </div>

        <form method="POST" action="{{ route('manager.requests.finalize') }}" class="form-grid">
            @csrf
            <div class="form-grid-two">
                <div class="field">
                    <label for="from">С</label>
                    <input id="from" class="input" type="datetime-local" name="from" data-range-from value="{{ $export_from }}">
                </div>
                <div class="field">
                    <label for="to">По</label>
                    <input id="to" class="input" type="datetime-local" name="to" data-range-to value="{{ $export_to }}">
                </div>
            </div>

            <div class="form-actions">
                <button class="button-warm" type="submit">Перенести в финальный список</button>
                <button class="button-secondary" type="button" data-set-today-range>На сегодня (22:00–06:00)</button>
                <button class="button-ghost" type="submit" formmethod="GET" formaction="{{ route('manager.requests.index') }}">Обновить предпросмотр</button>
            </div>
        </form>

        <details class="ops-disclosure ops-disclosure--section" open>
            <summary>Предпросмотр диапазона</summary>
            <div class="export-preview" id="export-preview">
            <div class="export-preview__head">
                <strong>Предпросмотр (первые 50 строк)</strong>
                <span class="pill-count">{{ $countLabel($export_total ?? 0, 'строк') }}</span>
            </div>
            <div class="export-legend">
                <span class="legend-item legend-item--warning">Вне окна 22:00–06:00</span>
            </div>
            <div class="request-table export-table request-table--scroll">
                <div class="request-table__head">
                    <span>#</span>
                    <span>Дата + время</span>
                    <span>ФИО</span>
                    <span>Адрес подачи</span>
                    <span>Телефон</span>
                    <span>Действия</span>
                </div>

                @php
                    $nightFrom = now()->copy()->setTime(22, 0);
                    if (now()->hour < 6) {
                        $nightFrom = $nightFrom->subDay();
                    }
                    $nightTo = $nightFrom->copy()->addHours(8);
                @endphp
                @forelse ($export_preview as $index => $request)
                    @php
                        $outsideNight = $request->date_time
                            ? ! $request->date_time->between($nightFrom, $nightTo)
                            : false;
                    @endphp
                    <div class="request-row inline-edit {{ $outsideNight ? 'row-warning' : '' }}">
                        <div data-label="#"> {{ ($export_preview->currentPage() - 1) * $export_preview->perPage() + $index + 1 }}</div>
                        <div data-label="Дата + время">
                            <input class="inline-input" type="datetime-local" name="date_time" value="{{ optional($request->date_time)->format('Y-m-d\\TH:i') }}" form="update-final-{{ $request->id }}" required>
                        </div>
                        <div data-label="ФИО">
                            <textarea class="inline-textarea" name="full_name" form="update-final-{{ $request->id }}" rows="2" required>{{ $displayName($request->full_name) }}</textarea>
                        </div>
                        <div data-label="Адрес подачи">
                            <textarea class="inline-textarea" name="address_raw" form="update-final-{{ $request->id }}" rows="2" required>{{ $request->address_raw }}</textarea>
                        </div>
                        <div data-label="Телефон">
                            <input class="inline-input" type="text" name="phone" value="{{ $request->phone }}" form="update-final-{{ $request->id }}" required>
                        </div>
                        <div class="request-row__actions" data-label="Действия">
                            <form id="update-final-{{ $request->id }}" method="POST" action="{{ route('manager.requests.update-final', $request) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="from" value="{{ $export_from }}">
                                <input type="hidden" name="to" value="{{ $export_to }}">
                                <input type="hidden" name="export_page" value="{{ $export_preview->currentPage() }}">
                                <button class="button" type="submit">Сохранить</button>
                            </form>
                            @if ($currentUser?->role?->name === 'Admin')
                                <form method="POST" action="{{ route('manager.requests.destroy-final', $request) }}" data-confirm-delete="Удалить финальную запись навсегда? Это действие нельзя отменить.">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="from" value="{{ $export_from }}">
                                    <input type="hidden" name="to" value="{{ $export_to }}">
                                    <input type="hidden" name="export_page" value="{{ $export_preview->currentPage() }}">
                                    <button class="button-danger" type="submit">Удалить</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        Нет записей в выбранном диапазоне.
                    </div>
                @endforelse
            </div>
            @if ($export_preview->lastPage() > 1)
                <div class="pagination">
                    <span class="muted">Страница {{ $export_preview->currentPage() }} из {{ $export_preview->lastPage() }}</span>
                    <div class="pagination__actions">
                        @if ($export_preview->onFirstPage())
                            <span class="button-ghost">Назад</span>
                        @else
                            <a class="button-ghost" href="{{ $export_preview->previousPageUrl() }}">Назад</a>
                        @endif

                        @if ($export_preview->hasMorePages())
                            <a class="button-ghost" href="{{ $export_preview->nextPageUrl() }}">Вперёд</a>
                        @else
                            <span class="button-ghost">Вперёд</span>
                        @endif
                    </div>
                </div>
            @endif
            </div>
        </details>
    </section>

    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Выгрузка</span>
                <h2>CSV для перевозчика</h2>
                <p>Файл с заголовками: номер по порядку, дата+время, ФИО, адрес подачи, телефон.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('manager.requests.export-csv') }}" class="form-grid">
            @csrf
            <div class="form-grid-two">
                <div class="field">
                    <label for="export_from">С</label>
                    <input id="export_from" class="input" type="datetime-local" name="from" data-range-from value="{{ $export_from }}">
                </div>
                <div class="field">
                    <label for="export_to">По</label>
                    <input id="export_to" class="input" type="datetime-local" name="to" data-range-to value="{{ $export_to }}">
                </div>
            </div>

            <div class="form-actions">
                <button class="button" type="submit">Скачать CSV</button>
                <button class="button-secondary" type="button" data-set-today-range>На сегодня (22:00–06:00)</button>
            </div>
        </form>
    </section>


    </div>
</x-layouts.portal-vtb>
