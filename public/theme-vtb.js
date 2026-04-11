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
});
