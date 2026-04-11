@php
    $tempRequests = $dashboard['temp_requests'];
    $finalRequests = $dashboard['requests'];
    $pendingCount = $tempRequests->where('status', \App\Enums\RequestStatus::Pending)->count();
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
    title="Кабинет сотрудника"
    heading="Личный кабинет сотрудника"
    subheading="Подавайте новые поездки, отслеживайте согласование и держите под рукой историю уже подтверждённых поездок."
    :current-user="$currentUser"
>
    <section class="dashboard-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Новая поездка</span>
                    <h2>Оформить заявку</h2>
                    <p>После отправки заявка попадёт в буфер согласования руководителя.</p>
                </div>
                <span class="pill-count">Маршрут по заявке</span>
            </div>

            <form method="POST" action="{{ route('employee.requests.store') }}" class="form-grid">
                @csrf
                <div class="form-grid-two">
                    <div class="field">
                        <label for="full_name">ФИО</label>
                        <input id="full_name" class="input" type="text" name="full_name" value="{{ old('full_name', $currentUser->full_name) }}" required>
                    </div>
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" class="input" type="text" name="phone" value="{{ old('phone', $currentUser->phone) }}" required>
                    </div>
                </div>

                <div class="field">
                    <label for="address_raw">Адрес подачи машины</label>
                    <textarea id="address_raw" class="textarea" name="address_raw" required>{{ old('address_raw', $currentUser->default_address) }}</textarea>
                    <small>Укажите адрес, где сотрудника нужно забрать. Позже добавим отдельное поле для редких исключений по адресу отправления.</small>
                </div>

                <div class="field">
                    <label for="date_time">Дата и время такси</label>
                    <input id="date_time" class="input" type="datetime-local" name="date_time" value="{{ old('date_time') }}" required>
                </div>

                <div class="split-note">
                    <strong>Как это работает сейчас</strong>
                    <span>Пока заявка находится в статусе «На согласовании», её можно отменить из своего кабинета.</span>
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Отправить на согласование</button>
                </div>
            </form>
        </article>

        <aside class="page-stack">
            <section class="hero-banner" data-reveal>
                <span class="kicker">Мой поток</span>
                <h2>Видимость по заявкам без звонков и переписки.</h2>
                <p>Сразу видно, сколько поездок ждут решения, что уже одобрено и что перенесено в финальный слой.</p>
                <div class="metric-grid">
                    <div class="stat-card">
                        <span class="stat-label">В буфере</span>
                        <div class="stat-value">{{ $tempRequests->count() }}</div>
                        <div class="stat-note">Все временные заявки по вашему аккаунту.</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">На согласовании</span>
                        <div class="stat-value">{{ $pendingCount }}</div>
                        <div class="stat-note">Их ещё можно отменить.</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Финальные</span>
                        <div class="stat-value">{{ $finalRequests->count() }}</div>
                        <div class="stat-note">Уже перенесены в основной слой.</div>
                    </div>
                </div>
            </section>

            <section class="panel" data-reveal>
                <div class="panel-header">
                    <div>
                        <span class="kicker">Подсказка</span>
                        <h2>Что важно для выгрузки</h2>
                    </div>
                </div>

                <div class="feature-list">
                    <div class="feature-item">
                        <strong>Телефон лучше в формате 89131234567</strong>
                        <p>Именно такой формат планируется использовать в итоговом CSV для перевозчика.</p>
                    </div>
                    <div class="feature-item">
                        <strong>Одобренная заявка ещё не финальна</strong>
                        <p>Финальной она станет после переноса руководителем в основную таблицу.</p>
                    </div>
                    <div class="feature-item">
                        <strong>История всегда под рукой</strong>
                        <p>Ниже видны и временные, и уже окончательно перенесённые поездки.</p>
                    </div>
                </div>
            </section>
        </aside>
    </section>

    <section class="dashboard-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Буфер</span>
                    <h2>Текущие заявки на согласовании</h2>
                    <p>Здесь видно, что именно сейчас находится во временном слое по вашему профилю.</p>
                </div>
                <span class="pill-count">{{ $tempRequests->count() }}</span>
            </div>

            <div class="list-stack">
                @forelse ($tempRequests as $request)
                    <article class="list-card">
                        <div class="list-head">
                            <div>
                                <strong>{{ $displayName($request->full_name) }}</strong>
                                <div class="list-meta">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</div>
                            </div>
                            <x-status-pill :status="$request->status" />
                        </div>

                        <div class="list-address">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>

                        @if ($request->rejection_reason)
                            <div class="callout callout-danger">{{ $request->rejection_reason }}</div>
                        @endif

                        @if ($request->manager_comment)
                            <div class="callout callout-info">{{ $request->manager_comment }}</div>
                        @endif

                        @if ($request->status === \App\Enums\RequestStatus::Pending)
                            <form method="POST" action="{{ route('employee.requests.cancel', $request) }}" class="form-grid" style="margin-top: 16px;">
                                @csrf
                                @method('PATCH')
                                <div class="field">
                                    <label for="cancel_reason_{{ $request->id }}">Причина отмены</label>
                                    <input id="cancel_reason_{{ $request->id }}" class="input" type="text" name="reason" placeholder="Например: поездка больше не нужна">
                                </div>
                                <div class="form-actions">
                                    <button class="button-danger" type="submit">Отменить заявку</button>
                                </div>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="empty-state">
                        Пока нет заявок в буфере. Создайте первую поездку в форме выше.
                    </div>
                @endforelse
            </div>
        </article>

        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">История</span>
                    <h2>Финальные заявки</h2>
                    <p>Эти записи уже перенесены в основной слой `requests` и готовы к дальнейшей выгрузке.</p>
                </div>
                <span class="pill-count">{{ $finalRequests->count() }}</span>
            </div>

            <div class="list-stack">
                @forelse ($finalRequests as $request)
                    <article class="list-card">
                        <div class="list-head">
                            <div>
                                <strong>{{ $displayName($request->full_name) }}</strong>
                                <div class="list-meta">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</div>
                            </div>
                            <x-status-pill :status="$request->status" />
                        </div>
                        <div class="list-address">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>
                    </article>
                @empty
                    <div class="empty-state">
                        Финальных заявок пока нет. Как только руководитель перенесёт одобренные записи, они появятся здесь.
                    </div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.portal-vtb>
