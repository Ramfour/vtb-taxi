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
    @php
        $schedule = $currentUser->commuteSchedule;
        $selectedDays = [];
        if ($schedule) {
            for ($i = 1; $i <= 7; $i++) {
                $bit = 1 << ($i - 1);
                if (($schedule->days_mask & $bit) !== 0) {
                    $selectedDays[] = $i;
                }
            }
        }
        $dayLabels = [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс',
        ];
    @endphp

    <section class="page-stack employee-requests-stack">
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
                        <input id="full_name" class="input" type="text" name="full_name" value="{{ old('full_name', $displayName($currentUser->full_name)) }}" required>
                    </div>
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" class="input" type="text" name="phone" value="{{ old('phone', $currentUser->phone) }}" required>
                    </div>
                </div>

                <div class="field">
                    <label for="address_raw">Адрес подачи машины</label>
                    <textarea id="address_raw" class="textarea" name="address_raw" required data-default-address="{{ $currentUser->latestAddress?->address ?? '' }}" data-has-old="{{ old('address_raw') ? '1' : '0' }}">{{ old('address_raw') }}</textarea>
                    <small>Укажите адрес, где сотрудника нужно забрать. Последний введённый адрес сохраняется для следующей поездки.</small>
                </div>

                <div class="field">
                    <label for="date_time">Дата и время такси</label>
                    <input id="date_time" class="input" type="datetime-local" name="date_time" value="{{ old('date_time') }}" required>
                </div>

                <div class="address-presets" data-address-presets data-employee="{{ $currentUser->employee_number }}">
                    <input type="hidden" data-address-endpoint="{{ route('employee.addresses.index') }}">
                    <div class="address-presets__head">
                        <span class="field-label">Быстрые адреса</span>
                    </div>
                    <div class="field">
                        <label for="address_select">Выбрать из списка</label>
                        <select id="address_select" class="select" data-address-select>
                            <option value="">Выберите адрес</option>
                        </select>
                    </div>
                    <div class="address-presets__controls">
                        <input class="input" type="text" placeholder="Добавить адрес в быстрые" data-address-input>
                        <button class="button-ghost" type="button" data-address-add>Добавить</button>
                        <button class="button-ghost" type="button" data-address-add-current>Добавить текущий</button>
                    </div>
                    <div class="address-presets__list" data-address-list></div>
                    <small class="muted">Клик по адресу подставит его в поле. Адреса можно удалять, список хранится в браузере этого устройства.</small>
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
            <section class="panel panel--compact ops-side" data-reveal>
                <div class="panel-header panel-header--tight">
                    <div>
                        <span class="kicker">Состояние</span>
                        <h2>Мои заявки</h2>
                        <p>Коротко по статусам и правилам заполнения.</p>
                    </div>
                </div>

                <div class="ops-kpi-strip ops-kpi-strip--stack" aria-label="Сводка по заявкам">
                    <span class="ops-badge">В буфере: {{ $tempRequests->count() }}</span>
                    <span class="ops-badge ops-badge--pending">На согласовании: {{ $pendingCount }}</span>
                    <span class="ops-badge ops-badge--approved">Финальные: {{ $finalRequests->count() }}</span>
                </div>

                <div class="ops-rulelist" aria-label="Правила заполнения">
                    <div class="ops-rule">
                        <strong>Телефон</strong>
                        <span>Желательно в формате <code>89131234567</code>.</span>
                    </div>
                    <div class="ops-rule">
                        <strong>Одобрено != финально</strong>
                        <span>Финальной заявка станет после переноса руководителем.</span>
                    </div>
                    <div class="ops-rule">
                        <strong>История</strong>
                        <span>Ниже видны и временные, и финальные поездки.</span>
                    </div>
                </div>
            </section>

            <section class="panel panel--compact" data-reveal>
                <div class="panel-header panel-header--tight">
                    <div>
                        <span class="kicker">План</span>
                        <h2>Расписание “уехать домой”</h2>
                        <p>Заявки не отправляются сразу. В день окна D заявка будет создана автоматически в 12:00 и попадёт руководителю.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('employee.commute-schedule.upsert') }}" class="form-grid form-grid--tight">
                    @csrf
                    <input type="hidden" name="exceptions_json" value="{{ old('exceptions_json', '') }}" data-commute-exceptions>
                    <div class="field">
                        <label class="field-label">Дни недели</label>
                        <div class="weekday-pills" role="group" aria-label="Дни недели">
                            @foreach ($dayLabels as $value => $label)
                                <label class="weekday-pill">
                                    <input type="checkbox" name="days[]" value="{{ $value }}" @checked(in_array($value, old('days', $selectedDays), true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <small class="muted">Пример: Пн–Пт. Исключения по конкретным датам добавим следующим шагом.</small>
                    </div>

                    <div class="form-grid-two">
                        <div class="field">
                            <label for="default_time">Время выезда</label>
                            <input id="default_time" class="input" type="time" name="default_time" value="{{ old('default_time', $schedule?->default_time?->format('H:i') ?? '23:00') }}" required>
                        </div>
                        <div class="field">
                            <label for="default_address_raw">Адрес по умолчанию</label>
                            <input id="default_address_raw" class="input" type="text" name="default_address_raw" value="{{ old('default_address_raw', $schedule?->default_address_raw ?? $currentUser->latestAddress?->address ?? '') }}" placeholder="Например: Новосибирск, Красный проспект, 25">
                        </div>
                    </div>
                    <small class="muted">Допустимо только 22:00–06:00 (00:00–06:00 относится к окну D).</small>

                    <div class="form-actions">
                        <button class="button-secondary" type="button" data-commute-wizard-open>Открыть календарь</button>
                        <button class="button" type="submit">Сохранить расписание</button>
                    </div>
                </form>
            </section>

            <div class="modal-overlay" data-commute-modal>
                <div class="modal-card modal-card--wide">
                    <div class="modal-head">
                        <strong>Расписание: “уехать домой”</strong>
                        <button class="modal-close" type="button" data-commute-modal-close>Закрыть</button>
                    </div>

                    <div class="modal-body">
                        <div class="wizard" data-commute-wizard>
                            <div class="wizard-steps">
                                <span class="wizard-step is-active" data-step-indicator="1">1. Календарь</span>
                                <span class="wizard-step" data-step-indicator="2">2. Время</span>
                                <span class="wizard-step" data-step-indicator="3">3. Правки</span>
                            </div>

                            <section class="wizard-pane" data-step="1">
                                <div class="wizard-title">
                                    <strong>Выберите дни (ближайшие 28 дней)</strong>
                                    <span class="muted">Клик по дате меняет режим: если в этот день запланирована поездка по расписанию, клик отменит её; если поездка не запланирована, клик добавит поездку на этот день.</span>
                                </div>
                                <div class="calendar-grid" data-calendar-grid></div>
                            </section>

                            <section class="wizard-pane" data-step="2" hidden>
                                <div class="wizard-title">
                                    <strong>Одно время для всех</strong>
                                    <span class="muted">Допустимо только 22:00–06:00. Время 00:00–06:00 относится к окну D (но календарно это следующий день).</span>
                                </div>
                                <div class="form-grid-two">
                                    <div class="field">
                                        <label for="wizard_default_time">Время выезда</label>
                                        <input id="wizard_default_time" class="input" type="time" value="{{ $schedule?->default_time?->format('H:i') ?? '23:00' }}" data-wizard-default-time>
                                    </div>
                                    <div class="field">
                                        <label for="wizard_default_address">Адрес по умолчанию</label>
                                        <input id="wizard_default_address" class="input" type="text" value="{{ $schedule?->default_address_raw ?? $currentUser->latestAddress?->address ?? '' }}" data-wizard-default-address>
                                    </div>
                                </div>
                            </section>

                            <section class="wizard-pane" data-step="3" hidden>
                                <div class="wizard-title">
                                    <strong>Изменить отдельные дни</strong>
                                    <span class="muted">Добавляйте исключения по датам окна D: “пропуск” или другое время.</span>
                                </div>
                                <div class="form-grid-two">
                                    <div class="field">
                                        <label for="exception_date">Добавить день</label>
                                        <input id="exception_date" class="input" type="date" data-exception-date>
                                        <small class="muted">Дата окна D (22:00–06:00).</small>
                                    </div>
                                    <div class="form-actions" style="align-self: end;">
                                        <button class="button-secondary" type="button" data-exception-add>Добавить</button>
                                    </div>
                                </div>
                                <div class="exception-table exception-table--scroll" data-exception-table aria-label="Исключения">
                                    <div class="exception-table__head">
                                        <span>Окно D</span>
                                        <span>Статус</span>
                                        <span>Время</span>
                                        <span>Действия</span>
                                    </div>
                                    <div class="exception-empty muted" data-exception-empty>Пока нет изменений по датам.</div>
                                </div>
                            </section>

                            <div class="wizard-actions">
                                <button class="button-ghost" type="button" data-wizard-back disabled>Назад</button>
                                <button class="button-secondary" type="button" data-wizard-next>Далее</button>
                                <button class="button" type="button" data-wizard-apply hidden>Применить</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <details class="compact-disclosure panel" data-reveal hidden>
                <summary>
                    <span>
                        <span class="kicker">Аккаунт</span>
                        <strong>Сменить пароль</strong>
                    </span>
                    <span class="disclosure-meta">
                        <span class="disclosure-hint">Нажмите, чтобы раскрыть</span>
                    </span>
                </summary>

                <div class="disclosure-body">
                    <form method="POST" action="{{ route('account.password.update') }}" class="form-grid">
                        @csrf
                        <div class="field">
                            <label for="current_password_employee">Текущий пароль</label>
                            <div class="password-field">
                                <input id="current_password_employee" class="input" type="password" name="current_password" required>
                                <button class="password-toggle" type="button" data-password-toggle>Показать</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="new_password_employee">Новый пароль</label>
                            <div class="password-field">
                                <input id="new_password_employee" class="input" type="password" name="password" required>
                                <button class="password-toggle" type="button" data-password-toggle>Показать</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="new_password_employee_confirmation">Подтверждение нового пароля</label>
                            <div class="password-field">
                                <input id="new_password_employee_confirmation" class="input" type="password" name="password_confirmation" required>
                                <button class="password-toggle" type="button" data-password-toggle>Показать</button>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button class="button" type="submit">Обновить пароль</button>
                        </div>
                    </form>
                </div>
            </details>
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

            <div class="request-table request-table--scroll request-table--employee">
                <div class="request-table__head">
                    <span>Дата + время</span>
                    <span>Адрес подачи</span>
                    <span>Статус</span>
                    <span>Действия</span>
                </div>

                @forelse ($tempRequests as $request)
                    <div class="request-row">
                        <div data-label="Дата + время">
                            <strong>{{ $request->date_time?->format('d.m.Y H:i') }}</strong>
                            <div class="muted">{{ $request->phone }}</div>
                        </div>
                        <div data-label="Адрес подачи">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>
                        <div data-label="Статус"><x-status-pill :status="$request->status" /></div>
                        <div data-label="Действия">
                            @if ($request->status === \App\Enums\RequestStatus::Pending)
                                <details class="ops-disclosure ops-disclosure--inline">
                                    <summary>Отменить</summary>
                                    <div class="disclosure-body">
                                        <form method="POST" action="{{ route('employee.requests.cancel', $request) }}" class="form-grid">
                                            @csrf
                                            @method('PATCH')
                                            <div class="field">
                                                <label for="cancel_reason_{{ $request->id }}">Причина отмены</label>
                                                <input id="cancel_reason_{{ $request->id }}" class="input" type="text" name="reason" placeholder="Например: поездка больше не нужна">
                                            </div>
                                            <div class="form-actions">
                                                <button class="button-danger" type="submit">Подтвердить отмену</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </div>

                        @if ($request->manager_comment || $request->rejection_reason)
                            <div class="request-row__notes">
                                @if ($request->rejection_reason)
                                    <div class="callout callout-danger">{{ $request->rejection_reason }}</div>
                                @endif
                                @if ($request->manager_comment)
                                    <div class="callout callout-info">{{ $request->manager_comment }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
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

            <div class="request-table request-table--scroll request-table--employee-final">
                <div class="request-table__head">
                    <span>Дата + время</span>
                    <span>Адрес подачи</span>
                    <span>Статус</span>
                </div>

                @forelse ($finalRequests as $request)
                    <div class="request-row">
                        <div data-label="Дата + время">
                            <strong>{{ $request->date_time?->format('d.m.Y H:i') }}</strong>
                            <div class="muted">{{ $request->phone }}</div>
                        </div>
                        <div data-label="Адрес подачи">{{ $displayAddress($request->address_norm ?? $request->address_raw) }}</div>
                        <div data-label="Статус"><x-status-pill :status="$request->status" /></div>
                    </div>
                @empty
                    <div class="empty-state">
                        Финальных заявок пока нет. Как только руководитель перенесёт одобренные записи, они появятся здесь.
                    </div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.portal-vtb>
