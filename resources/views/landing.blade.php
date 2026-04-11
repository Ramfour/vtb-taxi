<x-layouts.portal
    title="VTB Taxi"
    heading="Система заявок на корпоративное такси"
    subheading="Веб-версия уже поддерживает вход по табельному номеру, регистрацию сотрудников по приглашению и раздельные кабинеты сотрудника и руководителя."
>
    <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <h2 class="text-xl font-semibold text-white">Как сейчас работает система</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-3xl border border-cyan-400/20 bg-cyan-500/10 p-4">
                    <p class="text-sm font-semibold text-cyan-200">1. Вход</p>
                    <p class="mt-2 text-sm text-slate-200">Пользователь входит по табельному номеру и паролю. Сотрудника в систему заводит руководитель через приглашение.</p>
                </div>
                <div class="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-4">
                    <p class="text-sm font-semibold text-amber-200">2. Согласование</p>
                    <p class="mt-2 text-sm text-slate-200">Руководитель одобряет или отклоняет заявки прямо из панели.</p>
                </div>
                <div class="rounded-3xl border border-emerald-400/20 bg-emerald-500/10 p-4">
                    <p class="text-sm font-semibold text-emerald-200">3. Финализация</p>
                    <p class="mt-2 text-sm text-slate-200">Одобренные заявки переносятся из буфера в основную таблицу `requests`.</p>
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6 backdrop-blur">
            <h2 class="text-xl font-semibold text-white">Логика доступа</h2>
            <p class="mt-2 text-sm text-slate-300">Руководитель может работать и как руководитель, и как обычный сотрудник в одном аккаунте. Отдельное переключение ролей не требуется.</p>
            <a class="mt-5 inline-flex rounded-full bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" href="{{ route('login') }}">Перейти ко входу</a>
        </section>
    </div>
</x-layouts.portal>
