document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-theme-toggle]');
    const label = document.querySelector('[data-theme-label]');

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

    document.querySelectorAll('[data-set-today-range]').forEach((button) => {
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

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        window.setTimeout(() => {
            element.classList.add('is-visible');
        }, index * 60);
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
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

    document.querySelectorAll('input[type=\"datetime-local\"]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
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
});
