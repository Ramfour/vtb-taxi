@php
    $tempRequests = $dashboard['temp_requests'];
    $finalRequests = $dashboard['requests'];
    $pendingCount = $tempRequests->where('status', \App\Enums\RequestStatus::Pending)->count();
@endphp

<x-layouts.portal-vtb
    title="Кабинет сотрудника"
    heading="Личный кабинет сотрудника"
    subheading="Подавайте новые поездки, следите за согласованием и держите под рукой историю уже подтверждённых заявок."
    :current-user="$currentUser"
>
    <section class="dashboard-grid">
        <article class="panel" data-reveal>
            <div class="panel-header">
                <div>
                    <span class="kicker">Новая поездка</span>
                    <h2>Оформить заявку</h2>
                    <p>Заполните поездку на нужную дату и время. После отправки заявка сразу попадёт в буфер согласования руководителя.</p>
                </div>
                <span class="pill-count">По умолчанию: ВТБ</span>
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
                    <label for="address_raw">Куда должна приехать машина</label>
                    <textarea id="address_raw" class="textarea" name="address_raw" required>{{ old('address_raw', $currentUser->default_address) }}</textarea>
                    <small>Чем точнее адрес, тем проще будет будущая нормализация и выгрузка.</small>
                </div>

                <div class="field">
                    <label for="date_time">Дата и время такси</label>
                    <input id="date_time" class="input" type="datetime-local" name="date_time" value="{{ old('date_time') }}" required>
                </div>

                <div class="split-note">
                    <strong>Как это работает сейчас</strong>
                    <span>После отправки заявка появится в буфере согласования. Пока статус остаётся “На согласовании”, её можно отменить из своего кабинета.</span>
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
                <p>В кабинете сразу видно, сколько поездок ждут решения, какие уже одобрены и что ушло в финальный список.</p>
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
                        <p>Именно такой формат вы планируете использовать в итоговом CSV для перевозчика.</p>
                    </div>
                    <div class="feature-item">
                        <strong>Одобренная заявка ещё не финальна</strong>
                        <p>Финальной она станет после переноса руководителем в основную таблицу.</p>
                    </div>
                    <div class="feature-item">
                        <strong>История остаётся под рукой</strong>
                        <p>Внизу кабинета видны и временные, и уже окончательно перенесённые поездки.</p>
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
                                <strong>{{ $request->full_name }}</strong>
                                <div class="list-meta">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</div>
                            </div>
                            <x-status-pill :status="$request->status" />
                        </div>

                        <div class="list-address">{{ $request->address_norm ?? $request->address_raw }}</div>

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
                                <strong>{{ $request->full_name }}</strong>
                                <div class="list-meta">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</div>
                            </div>
                            <x-status-pill :status="$request->status" />
                        </div>
                        <div class="list-address">{{ $request->address_norm ?? $request->address_raw }}</div>
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
