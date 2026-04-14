<x-layouts.app
    title="VTB Taxi"
    heading="Стартовая страница"
    subheading="Вход выполняется по табельному номеру и паролю. Если доступа нет — попросите приглашение у руководителя."
>
    <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
        <h2 class="text-xl font-semibold text-white">Вход в систему</h2>
        <p class="mt-2 text-sm text-slate-300">Для продолжения перейдите к форме входа или используйте персональную ссылку приглашения.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a class="rounded-full bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" href="{{ route('login') }}">Войти</a>
        </div>
    </div>
</x-layouts.app>
