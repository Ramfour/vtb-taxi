document.addEventListener('DOMContentLoaded', () => {
    const vtbAjaxVersion = '1';
    const root = document.documentElement;
    const toggle = document.querySelector('[data-theme-toggle]');
    const label = document.querySelector('[data-theme-label]');
    const safe = (name, fn) => {
        try {
            fn();
        } catch (error) {
            console.error(`[vtb-ui] ${name} failed`, error);
        }
    };

    const applyTheme = (theme) => {
        root.dataset.theme = theme;

        if (label) {
            label.textContent = theme === 'light' ? 'Тёмная тема' : 'Светлая тема';
        }
    };

    applyTheme(root.dataset.theme === 'light' ? 'light' : 'dark');

    if (toggle) {
        toggle.addEventListener('click', () => {
            const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light';
            localStorage.setItem('vtb-theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    const bindTodayRange = (scope = document) => {
        scope.querySelectorAll('[data-set-today-range]').forEach((button) => {
            if (button.dataset.boundToday === '1') {
                return;
            }
            button.dataset.boundToday = '1';
            button.addEventListener('click', () => {
                const form = button.closest('form');
                const fromInput = form ? form.querySelector('[data-range-from]') : document.querySelector('#from');
                const toInput = form ? form.querySelector('[data-range-to]') : document.querySelector('#to');

                if (!fromInput || !toInput) {
                    return;
                }

                const now = new Date();
                const from = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 22, 0, 0);
                const to = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 6, 0, 0);

                const toLocalInput = (value) => {
                    const pad = (num) => String(num).padStart(2, '0');
                    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(value.getHours())}:${pad(value.getMinutes())}`;
                };

                fromInput.value = toLocalInput(from);
                toInput.value = toLocalInput(to);
            });
        });
    };

    const bindCopyButtons = (scope = document) => {
        scope.querySelectorAll('[data-copy-target]').forEach((button) => {
            if (button.dataset.boundCopy === '1') {
                return;
            }
            button.dataset.boundCopy = '1';
            button.addEventListener('click', async () => {
                const selector = button.getAttribute('data-copy-target');
                const target = selector ? document.querySelector(selector) : null;

                if (!target) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(target.textContent.trim());
                    const original = button.textContent;
                    button.textContent = 'Скопировано';

                    window.setTimeout(() => {
                        button.textContent = original;
                    }, 1800);
                } catch (error) {
                    button.textContent = 'Не удалось';
                }
            });
        });
    };

    const bindDateInputs = (scope = document) => {
        scope.querySelectorAll('input[type=\"datetime-local\"]').forEach((input) => {
            if (input.dataset.boundDate === '1') {
                return;
            }
            input.dataset.boundDate = '1';
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
        });
    };

    const bindPasswordToggles = (scope = document) => {
        scope.querySelectorAll('[data-password-toggle]').forEach((button) => {
            if (button.dataset.boundPassword === '1') {
                return;
            }
            button.dataset.boundPassword = '1';
            button.addEventListener('click', () => {
                const field = button.closest('.password-field');
                const input = field ? field.querySelector('input') : null;
                if (!input) {
                    return;
                }
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                button.textContent = isPassword ? 'Скрыть' : 'Показать';
            });
        });
    };

    const bindPasswordToggleAll = (scope = document) => {
        scope.querySelectorAll('[data-password-toggle-all]').forEach((button) => {
            if (button.dataset.boundPasswordAll === '1') {
                return;
            }
            button.dataset.boundPasswordAll = '1';
            button.addEventListener('click', () => {
                const form = button.closest('form');
                if (!form) {
                    return;
                }
                const inputs = Array.from(form.querySelectorAll('input[type="password"], input[type="text"]'));
                if (!inputs.length) {
                    return;
                }
                const shouldShow = inputs.some((input) => input.type === 'password');
                inputs.forEach((input) => {
                    input.type = shouldShow ? 'text' : 'password';
                });
                button.textContent = shouldShow ? 'Скрыть пароли' : 'Показать пароли';
            });
        });
    };

    const bindApproveScopeButtons = (scope = document) => {
        scope.querySelectorAll('[data-approve-scope]').forEach((button) => {
            if (button.dataset.boundApproveScope === '1') {
                return;
            }
            button.dataset.boundApproveScope = '1';
            button.addEventListener('click', () => {
                const form = button.closest('form');
                if (!form) {
                    return;
                }
                const hiddenInput = form.querySelector('[data-approve-scope-input]');
                if (!hiddenInput) {
                    return;
                }
                hiddenInput.value = button.getAttribute('data-approve-scope') || hiddenInput.value || 'selected';
            });
        });
    };

    const bindBulkSelectionCounter = (scope = document) => {
        const countEl = scope.querySelector('[data-selected-count]');
        if (!countEl || countEl.dataset.boundSelectedCount === '1') {
            return;
        }
        countEl.dataset.boundSelectedCount = '1';

        const clearButton = scope.querySelector('[data-clear-selection]');
        const checkboxes = Array.from(scope.querySelectorAll('input[type="checkbox"][name="request_ids[]"][form="bulk-approve-form"]'));

        const updateCount = () => {
            const selected = checkboxes.filter((cb) => cb.checked).length;
            countEl.textContent = `Выбрано: ${selected}`;
        };

        checkboxes.forEach((cb) => {
            cb.addEventListener('change', updateCount);
        });

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                checkboxes.forEach((cb) => {
                    if (!cb.disabled) {
                        cb.checked = false;
                    }
                });
                updateCount();
            });
        }

        updateCount();
    };

    document.querySelectorAll('[data-confirm-logout]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm-logout') || 'Вы уверены, что хотите выйти?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm-delete') || 'Удалить запись навсегда?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const bindCommuteWizard = () => {
        const modal = document.querySelector('[data-commute-modal]');
        const openButtons = Array.from(document.querySelectorAll('[data-commute-wizard-open]'));
        const closeBtn = document.querySelector('[data-commute-modal-close]');
        const wizard = document.querySelector('[data-commute-wizard]');

        if (!modal || !openButtons.length || !wizard) {
            return;
        }

        if (modal.dataset.boundCommuteWizard === '1') {
            return;
        }
        modal.dataset.boundCommuteWizard = '1';

        const panes = Array.from(wizard.querySelectorAll('[data-step]'));
        const indicators = Array.from(wizard.querySelectorAll('[data-step-indicator]'));
        const backBtn = wizard.querySelector('[data-wizard-back]');
        const nextBtn = wizard.querySelector('[data-wizard-next]');
        const applyBtn = wizard.querySelector('[data-wizard-apply]');
        const grid = wizard.querySelector('[data-calendar-grid]');
        const exceptionTable = wizard.querySelector('[data-exception-table]');
        const exceptionEmpty = wizard.querySelector('[data-exception-empty]');
        const wizardDefaultTime = wizard.querySelector('[data-wizard-default-time]');
        const wizardDefaultAddress = wizard.querySelector('[data-wizard-default-address]');
        const exceptionDateInput = wizard.querySelector('[data-exception-date]');
        const exceptionAddButton = wizard.querySelector('[data-exception-add]');

        const mainForm = openButtons[0].closest('form');
        const mainDefaultTime = mainForm ? mainForm.querySelector('input[name="default_time"]') : null;
        const mainDefaultAddress = mainForm ? mainForm.querySelector('input[name="default_address_raw"]') : null;
        const mainExceptions = mainForm ? mainForm.querySelector('[data-commute-exceptions]') : null;

        const exceptions = new Map(); // window_date -> {is_skipped,force_on,time_override,address_override_raw}

        const pad = (n) => String(n).padStart(2, '0');
        const fmtDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

        const weekdayShort = (d) => {
            // Russia: week starts on Monday.
            const map = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            const idx = (d.getDay() + 6) % 7; // Mon=0 ... Sun=6
            return map[idx];
        };

        const isSelectedByWeekday = (date) => {
            if (!mainForm) return true;
            const checkboxes = Array.from(mainForm.querySelectorAll('input[name="days[]"]'));
            const selected = new Set(checkboxes.filter((c) => c.checked).map((c) => Number(c.value)));
            const iso = date.getDay() === 0 ? 7 : date.getDay(); // 1..7
            return selected.has(iso);
        };

        const ensureGrid = () => {
            if (!grid) return;
            grid.innerHTML = '';
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());

            // Align the first date to the correct weekday column (Mon..Sun).
            const startIdx = (start.getDay() + 6) % 7; // Mon=0 ... Sun=6
            for (let i = 0; i < startIdx; i++) {
                const blank = document.createElement('div');
                blank.className = 'calendar-day calendar-day--blank';
                blank.setAttribute('aria-hidden', 'true');
                grid.appendChild(blank);
            }

            for (let i = 0; i < 28; i++) {
                const d = new Date(start);
                d.setDate(start.getDate() + i);
                const windowDate = fmtDate(d);
                const ex = exceptions.get(windowDate);
                const baseOn = isSelectedByWeekday(d);
                const selected = (() => {
                    if (!ex) return baseOn;
                    if (ex.force_on) return true;
                    if (ex.is_skipped) return false;
                    return baseOn || !!ex.time_override || !!ex.address_override_raw;
                })();

                const card = document.createElement('button');
                card.type = 'button';
                card.className = `calendar-day${selected ? '' : ' is-off'}`;
                card.dataset.windowDate = windowDate;

                const dateEl = document.createElement('div');
                dateEl.className = 'calendar-day__date';
                dateEl.textContent = `${pad(d.getDate())}.${pad(d.getMonth() + 1)} (${weekdayShort(d)})`;

                const meta = document.createElement('div');
                meta.className = 'calendar-day__meta';
                meta.textContent = (() => {
                    if (selected) {
                        if (!baseOn && ex && ex.force_on) return 'Едем';
                        if (ex && (ex.time_override || ex.address_override_raw)) return 'Едем (правка)';
                        return 'Едем';
                    }
                    if (baseOn && ex && ex.is_skipped) return 'Отмена';
                    return 'Нет';
                })();

                card.appendChild(dateEl);
                card.appendChild(meta);

                card.addEventListener('click', () => {
                    const current = exceptions.get(windowDate);
                    if (baseOn) {
                        // Base schedule has a ride: click toggles "cancel ride".
                        if (current && current.is_skipped) {
                            exceptions.delete(windowDate);
                        } else {
                            exceptions.set(windowDate, {
                                is_skipped: true,
                                force_on: false,
                                time_override: '',
                                address_override_raw: '',
                            });
                        }
                    } else {
                        // Base schedule has no ride: click toggles "add ride".
                        if (current && current.force_on) {
                            exceptions.delete(windowDate);
                        } else {
                            const defaultTime = (wizardDefaultTime && wizardDefaultTime.value) || (mainDefaultTime && mainDefaultTime.value) || '23:00';
                            exceptions.set(windowDate, {
                                is_skipped: false,
                                force_on: true,
                                time_override: (current && current.time_override) || defaultTime,
                                address_override_raw: (current && current.address_override_raw) || '',
                            });
                        }
                    }
                    ensureGrid();
                    renderExceptions();
                });

                grid.appendChild(card);
            }
        };

        const renderExceptions = () => {
            if (!exceptionTable) return;
            Array.from(exceptionTable.querySelectorAll('.exception-row')).forEach((r) => r.remove());

            const meaningful = Array.from(exceptions.entries())
                .map(([wd, ex]) => ({ wd, ex }))
                .filter(({ ex }) => ex && (ex.is_skipped || ex.force_on || ex.time_override || ex.address_override_raw))
                .sort((a, b) => a.wd.localeCompare(b.wd));

            if (exceptionEmpty) {
                exceptionEmpty.style.display = meaningful.length ? 'none' : 'block';
            }

            meaningful.forEach(({ wd, ex }) => {
                const row = document.createElement('div');
                row.className = 'exception-row';

                const c1 = document.createElement('div');
                c1.innerHTML = `<strong>${wd}</strong>`;

                const c2 = document.createElement('div');
                const skipLabel = document.createElement('label');
                skipLabel.className = 'weekday-pill';
                const skip = document.createElement('input');
                skip.type = 'checkbox';
                skip.checked = !!ex.is_skipped;
                const skipText = document.createElement('span');
                skipText.textContent = 'Отменить';
                skip.addEventListener('change', () => {
                    exceptions.set(wd, { ...ex, is_skipped: skip.checked, force_on: skip.checked ? false : ex.force_on });
                    ensureGrid();
                    renderExceptions();
                });
                skipLabel.appendChild(skip);
                skipLabel.appendChild(skipText);
                c2.appendChild(skipLabel);

                const c3 = document.createElement('div');
                const time = document.createElement('input');
                time.type = 'time';
                time.className = 'input';
                time.value = ex.time_override || (wizardDefaultTime ? wizardDefaultTime.value : '');
                time.disabled = !!ex.is_skipped;
                time.addEventListener('change', () => {
                    const val = time.value || '';
                    exceptions.set(wd, { ...ex, time_override: val });
                });
                c3.appendChild(time);

                const c4 = document.createElement('div');
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button-ghost';
                remove.textContent = 'Удалить';
                remove.addEventListener('click', () => {
                    exceptions.delete(wd);
                    ensureGrid();
                    renderExceptions();
                });
                c4.appendChild(remove);

                row.appendChild(c1);
                row.appendChild(c2);
                row.appendChild(c3);
                row.appendChild(c4);

                exceptionTable.appendChild(row);
            });
        };

        const setStep = (n) => {
            panes.forEach((p) => {
                p.hidden = String(p.dataset.step) !== String(n);
            });
            indicators.forEach((i) => {
                i.classList.toggle('is-active', i.getAttribute('data-step-indicator') === String(n));
            });
            if (backBtn) backBtn.disabled = n === 1;
            if (nextBtn) nextBtn.hidden = n === 3;
            if (applyBtn) applyBtn.hidden = n !== 3;
        };

        let step = 1;

        const open = () => {
            modal.classList.add('is-open');
            step = 1;
            setStep(step);
            ensureGrid();
            renderExceptions();
        };

        const close = () => modal.classList.remove('is-open');

        openButtons.forEach((btn) => btn.addEventListener('click', open));
        if (closeBtn) closeBtn.addEventListener('click', close);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });

        if (backBtn) backBtn.addEventListener('click', () => {
            step = Math.max(1, step - 1);
            setStep(step);
        });

        if (nextBtn) nextBtn.addEventListener('click', () => {
            step = Math.min(3, step + 1);
            setStep(step);
            if (step === 3) renderExceptions();
        });

        if (applyBtn) applyBtn.addEventListener('click', () => {
            if (mainDefaultTime && wizardDefaultTime) mainDefaultTime.value = wizardDefaultTime.value;
            if (mainDefaultAddress && wizardDefaultAddress) mainDefaultAddress.value = wizardDefaultAddress.value;
            if (mainExceptions) {
                const payload = Array.from(exceptions.entries())
                    .map(([window_date, ex]) => ({
                        window_date,
                        is_skipped: !!ex.is_skipped,
                        time_override: ex.time_override || null,
                        address_override_raw: ex.address_override_raw || null,
                    }))
                    .filter((row) => row.is_skipped || row.time_override || row.address_override_raw);
                mainExceptions.value = payload.length ? JSON.stringify(payload) : '';
            }
            close();
        });

        if (exceptionAddButton && exceptionDateInput) {
            exceptionAddButton.addEventListener('click', () => {
                const wd = String(exceptionDateInput.value || '').trim();
                if (!wd) return;
                const current = exceptions.get(wd);
                const next = current || {
                    is_skipped: false,
                    force_on: true,
                    time_override: (wizardDefaultTime && wizardDefaultTime.value) || '',
                    address_override_raw: '',
                };
                exceptions.set(wd, next);
                ensureGrid();
                renderExceptions();
            });
        }
    };

    safe('bindTodayRange', () => bindTodayRange());
    safe('bindBulkSelectionCounter', () => bindBulkSelectionCounter());
    safe('bindCommuteWizard', () => bindCommuteWizard());

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        window.setTimeout(() => {
            element.classList.add('is-visible');
        }, index * 60);
    });

    safe('bindCopyButtons', () => bindCopyButtons());

    const qrModal = document.querySelector('[data-qr-modal]');
    const qrPreview = document.querySelector('[data-qr-preview]');
    const qrCaption = document.querySelector('[data-qr-caption]');
    const qrClose = document.querySelector('[data-qr-close]');

    const closeQr = () => {
        if (!qrModal) {
            return;
        }
        qrModal.classList.remove('is-open');
        if (qrPreview) {
            qrPreview.innerHTML = '';
        }
        if (qrCaption) {
            qrCaption.textContent = '';
        }
    };

    if (qrModal) {
        qrModal.addEventListener('click', (event) => {
            if (event.target === qrModal) {
                closeQr();
            }
        });
    }

    if (qrClose) {
        qrClose.addEventListener('click', closeQr);
    }

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-qr-link]') : null;
        if (!button) {
            return;
        }
        if (!qrModal || !qrPreview || !qrCaption) {
            return;
        }
        const link = button.getAttribute('data-qr-link') || '';
        if (!link) {
            return;
        }
        qrPreview.innerHTML = '';
        const generator = typeof QRCode === 'function' ? QRCode : (window.qrcode && typeof window.qrcode === 'function' ? window.qrcode : null);

        if (generator) {
            const img = document.createElement('img');
            img.alt = 'QR';
            img.src = generator(link);
            qrPreview.appendChild(img);
            qrCaption.textContent = link;
        } else {
            const img = document.createElement('img');
            img.alt = 'QR';
            img.src = `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(link)}`;
            qrPreview.appendChild(img);
            qrCaption.textContent = link;
        }
        qrModal.classList.add('is-open');
    });

    document.querySelectorAll('[data-address-presets]').forEach((block) => {
        const employee = block.getAttribute('data-employee') || 'default';
        const form = block.closest('form');
        const textarea = form ? form.querySelector('#address_raw') : null;
        const listEl = block.querySelector('[data-address-list]');
        const addButton = block.querySelector('[data-address-add]');
        const addCurrentButton = block.querySelector('[data-address-add-current]');
        const input = block.querySelector('[data-address-input]');
        const select = block.querySelector('[data-address-select]');
        const endpointInput = block.querySelector('[data-address-endpoint]');

        if (!textarea || !listEl || !addButton || !addCurrentButton || !input || !select || !endpointInput) {
            return;
        }

        const baseEndpoint = endpointInput.getAttribute('data-address-endpoint') || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const buildUrl = (id = '') => (id ? `${baseEndpoint}/${id}` : baseEndpoint);
        let items = [];

        const setAddressValue = (value) => {
            textarea.value = value;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.focus();
        };

        const renderSelect = (items) => {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Выберите адрес';
            select.appendChild(placeholder);

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.address;
                option.textContent = item.address;
                select.appendChild(option);
            });

            select.disabled = items.length === 0;
        };

        const renderList = (items) => {
            listEl.innerHTML = '';

            if (!items.length) {
                const empty = document.createElement('span');
                empty.className = 'preset-empty';
                empty.textContent = 'Пока нет сохранённых адресов.';
                listEl.appendChild(empty);
                renderSelect([]);
                return;
            }

            items.forEach((item) => {
                const chip = document.createElement('div');
                chip.className = 'preset-chip';
                const address = item.address;

                const action = document.createElement('button');
                action.type = 'button';
                action.className = 'preset-chip__action';
                action.textContent = address;
                action.addEventListener('click', () => {
                    setAddressValue(address);
                });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'preset-chip__remove';
                remove.setAttribute('aria-label', 'Удалить адрес');
                remove.textContent = '×';
                remove.addEventListener('click', async () => {
                    if (!item.id) {
                        return;
                    }
                    const response = await fetch(buildUrl(item.id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    items = Array.isArray(data.addresses) ? data.addresses : [];
                    renderList(items);
                });

                chip.appendChild(action);
                chip.appendChild(remove);
                listEl.appendChild(chip);
            });
            renderSelect(items);
        };

        const defaultAddress = textarea.getAttribute('data-default-address') || '';
        const hasOld = textarea.getAttribute('data-has-old') === '1';

        if (!hasOld && !textarea.value.trim() && defaultAddress.trim()) {
            setAddressValue(defaultAddress.trim());
        }

        const addAddress = async (rawValue, useForField = false) => {
            const value = (rawValue || '').trim();
            if (!value) {
                return;
            }

            const response = await fetch(buildUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ address: value }),
            });

            if (response.ok) {
                const data = await response.json();
                items = Array.isArray(data.addresses) ? data.addresses : [];
                renderList(items);
            }

            if (useForField) {
                setAddressValue(value);
            }
        };

        addButton.addEventListener('click', () => {
            addAddress(input.value, true);
            input.value = '';
        });

        addCurrentButton.addEventListener('click', () => {
            addAddress(textarea.value, true);
        });

        select.addEventListener('change', () => {
            if (select.value) {
                setAddressValue(select.value);
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addAddress(input.value, true);
                input.value = '';
            }
        });

        const loadAddresses = async () => {
            const response = await fetch(buildUrl(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            items = Array.isArray(data.addresses) ? data.addresses : [];
            renderList(items);
        };

        loadAddresses();
    });

    bindDateInputs();
    bindPasswordToggles();
    bindPasswordToggleAll();
    bindApproveScopeButtons();

    const resolveAjaxTargets = (element) => {
        const rawTargets = element.getAttribute('data-ajax-targets');
        if (rawTargets) {
            return rawTargets.split(',').map((item) => item.trim()).filter(Boolean);
        }
        const container = element.closest('[data-ajax-container]');
        if (container) {
            const name = container.getAttribute('data-ajax-container');
            if (name) {
                return [`[data-ajax-container="${name}"]`];
            }
        }
        return [];
    };

    const swapAjaxTargets = (html, targets) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        targets.forEach((selector) => {
            const fresh = doc.querySelector(selector);
            const current = document.querySelector(selector);
            if (fresh && current) {
                current.replaceWith(fresh);
            }
        });
        document.querySelectorAll('[data-reveal]').forEach((element) => {
            element.classList.add('is-visible');
        });
        safe('bindCopyButtons', () => bindCopyButtons());
        safe('bindTodayRange', () => bindTodayRange());
        safe('bindDateInputs', () => bindDateInputs());
        safe('bindPasswordToggles', () => bindPasswordToggles());
        safe('bindPasswordToggleAll', () => bindPasswordToggleAll());
        safe('bindApproveScopeButtons', () => bindApproveScopeButtons());
        safe('bindBulkSelectionCounter', () => bindBulkSelectionCounter());
        safe('bindCommuteWizard', () => bindCommuteWizard());
    };

    const fetchHtml = async (url, options = {}) => {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {}),
                },
            });
            if (!response.ok) {
                return null;
            }
            return await response.text();
        } catch (error) {
            return null;
        }
    };

    document.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) {
            return;
        }
        const form = event.target instanceof Element ? event.target.closest('form[data-ajax]') : null;
        if (!form) {
            return;
        }

        event.preventDefault();

        const noFallback = form.hasAttribute('data-ajax-no-fallback');
        try {
            const targets = resolveAjaxTargets(form);
            if (!targets.length) {
                if (noFallback) {
                    return;
                }
                form.submit();
                return;
            }

            const method = (form.getAttribute('method') || 'POST').toUpperCase();
            const action = form.getAttribute('action') || window.location.href;
            const formData = new FormData(form);
            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            if (submitter && submitter.getAttribute('name')) {
                const name = submitter.getAttribute('name');
                const value = submitter.getAttribute('value') ?? '1';
                formData.set(name, value);
            }
            const scrollY = window.scrollY;
            let url = action;
            let options = {
                method,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
            };

            if (method === 'GET') {
                const urlObj = new URL(action, window.location.origin);
                formData.forEach((value, key) => {
                    urlObj.searchParams.set(key, value.toString());
                });
                url = urlObj.toString();
            } else {
                options.body = formData;
            }

            const html = await fetchHtml(url, options);
            if (!html) {
                if (noFallback) {
                    window.alert('Не удалось обновить без перезагрузки. Проверьте соединение и попробуйте ещё раз.');
                    return;
                }
                form.submit();
                return;
            }

            swapAjaxTargets(html, targets);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    window.scrollTo({ top: scrollY, left: 0, behavior: 'auto' });
                });
            });
            if (method === 'GET') {
                history.pushState({}, '', url);
            }
        } catch (error) {
            if (noFallback) {
                window.alert('Не удалось обновить без перезагрузки. Попробуйте ещё раз.');
                return;
            }
            form.submit();
        }
    });

    document.addEventListener('click', async (event) => {
        const ajaxLink = event.target instanceof Element ? event.target.closest('a[data-ajax-link]') : null;
        if (!ajaxLink) {
            return;
        }

        event.preventDefault();
        try {
            const targets = resolveAjaxTargets(ajaxLink);
            if (!targets.length) {
                window.location.href = ajaxLink.href;
                return;
            }

            const scrollY = window.scrollY;
            const html = await fetchHtml(ajaxLink.href);
            if (!html) {
                window.location.href = ajaxLink.href;
                return;
            }

            swapAjaxTargets(html, targets);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    window.scrollTo({ top: scrollY, left: 0, behavior: 'auto' });
                });
            });
            history.pushState({}, '', ajaxLink.href);
        } catch (error) {
            window.location.href = ajaxLink.href;
        }
    });

    window.addEventListener('popstate', async () => {
        const containers = document.querySelectorAll('[data-ajax-container]');
        if (!containers.length) {
            return;
        }
        const selectors = Array.from(containers)
            .map((container) => container.getAttribute('data-ajax-container'))
            .filter(Boolean)
            .map((name) => `[data-ajax-container="${name}"]`);
        const html = await fetchHtml(window.location.href);
        if (html) {
            swapAjaxTargets(html, selectors);
        }
    });

    const passwordModal = document.querySelector('[data-password-modal]');
    const openPasswordModal = document.querySelector('[data-password-modal-open]');
    const closePasswordModal = document.querySelector('[data-password-modal-close]');

    const closePassword = () => {
        if (!passwordModal) {
            return;
        }
        passwordModal.classList.remove('is-open');
    };

    if (openPasswordModal && passwordModal) {
        openPasswordModal.addEventListener('click', () => {
            passwordModal.classList.add('is-open');
        });
    }

    if (closePasswordModal) {
        closePasswordModal.addEventListener('click', closePassword);
    }

    if (passwordModal) {
        passwordModal.addEventListener('click', (event) => {
            if (event.target === passwordModal) {
                closePassword();
            }
        });
    }

});
