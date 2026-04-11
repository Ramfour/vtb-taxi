document.addEventListener('DOMContentLoaded', () => {
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
