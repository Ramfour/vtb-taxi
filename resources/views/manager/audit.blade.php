@php
    $displayName = function (?string $fullName): string {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) === 3 && preg_match('/(ич|вич|ьмиич|оглы|кызы|овна|евна|ична)$/u', $parts[1])) {
            return implode(' ', [$parts[2], $parts[0], $parts[1]]);
        }

        return $fullName;
    };
@endphp

<x-layouts.portal-vtb
    title="Аудит действий"
    heading="Аудит действий"
    subheading="История ключевых операций в системе: заявки, пользователи, приглашения, выгрузки."
    :current-user="$currentUser"
>
    <div>
    <section class="panel panel--compact" data-reveal>
        <div class="panel-header panel-header--tight">
            <div>
                <span class="kicker">Администратор</span>
                <h2>Журнал аудита</h2>
                <p>Проверьте кто и когда выполнял действия в системе. Фильтры работают вместе.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.audit.index') }}" class="form-grid">
            <div class="form-grid-two">
                <div class="field">
                    <label for="audit_search">Поиск (ФИО, табельный, действие)</label>
                    <input id="audit_search" class="input" type="text" name="q" value="{{ $search }}" placeholder="Например: одобрено, Иванов, 70320699">
                </div>
                <div class="field">
                    <label for="audit_action">Действие</label>
                    <select id="audit_action" class="select" name="action">
                        <option value="">Все действия</option>
                        @foreach ($actions as $actionItem)
                            <option value="{{ $actionItem['value'] }}" @selected($actionFilter === $actionItem['value'])>{{ $actionItem['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-grid-two">
                <div class="field">
                    <label for="audit_entity">Сущность</label>
                    <select id="audit_entity" class="select" name="entity">
                        <option value="">Все сущности</option>
                        @foreach ($entities as $entityItem)
                            <option value="{{ $entityItem }}" @selected($entityFilter === $entityItem)>{{ $entityItem }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Период</label>
                    <div class="form-grid-two">
                        <input class="input" type="datetime-local" name="from" value="{{ $from }}">
                        <input class="input" type="datetime-local" name="to" value="{{ $to }}">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button class="button" type="submit">Показать</button>
                <a class="button-ghost" href="{{ route('admin.audit.index') }}">Сбросить фильтры</a>
                <a class="button-secondary" href="{{ route('admin.audit.export', request()->query()) }}">Скачать CSV</a>
            </div>
        </form>

        <div class="audit-table">
            <div class="audit-table__head">
                <span>Дата</span>
                <span>Пользователь</span>
                <span>Действие</span>
                <span>Сущность</span>
                <span>IP</span>
                <span>Детали</span>
            </div>

            @forelse ($logs as $log)
                <div class="audit-row">
                    <div data-label="Дата">{{ $log->created_at?->format('d.m.Y H:i') }}</div>
                    <div data-label="Пользователь">
                        <strong>{{ $displayName($log->user?->full_name) }}</strong>
                        <span class="muted">{{ $log->user?->employee_number ?? '—' }}</span>
                    </div>
                    <div data-label="Действие">{{ $actionLabelMap[$log->action] ?? $log->action }}</div>
                    <div data-label="Сущность">{{ $log->entity_type }} @if($log->entity_id) #{{ $log->entity_id }} @endif</div>
                    <div data-label="IP">{{ $log->ip_address ?? '—' }}</div>
                    <div data-label="Детали">
                        @if ($log->old_values || $log->new_values)
                            <details class="compact-disclosure audit-details">
                                <summary>
                                    <span>Открыть</span>
                                    <span class="disclosure-meta">
                                        <span class="disclosure-hint">старое → новое</span>
                                    </span>
                                </summary>
                                <div class="disclosure-body">
                                    @if ($log->old_values)
                                        <div>
                                            <div class="muted">Было</div>
                                            <pre class="audit-json">{{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                    @if ($log->new_values)
                                        <div>
                                            <div class="muted">Стало</div>
                                            <pre class="audit-json">{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @else
                            <span class="muted">Нет данных</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    Записей аудита пока нет.
                </div>
            @endforelse
        </div>

        @if ($logs->lastPage() > 1)
            <div class="pagination">
                <span class="muted">Страница {{ $logs->currentPage() }} из {{ $logs->lastPage() }}</span>
                <div class="pagination__actions">
                    @if ($logs->onFirstPage())
                        <span class="button-ghost">Назад</span>
                    @else
                        <a class="button-ghost" href="{{ $logs->previousPageUrl() }}">Назад</a>
                    @endif

                    @if ($logs->hasMorePages())
                        <a class="button-ghost" href="{{ $logs->nextPageUrl() }}">Вперёд</a>
                    @else
                        <span class="button-ghost">Вперёд</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
    </div>
</x-layouts.portal-vtb>
