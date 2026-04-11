<x-layouts.app
    title="VTB Taxi MVP"
    heading="Стартовый экран MVP"
    subheading="Пока нет полноценной авторизации, поэтому для тестирования можно входить в роль сотрудника или руководителя прямо отсюда."
>
    <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <h2 class="text-xl font-semibold text-white">Как сейчас работает MVP</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-3xl border border-cyan-400/20 bg-cyan-500/10 p-4">
                    <p class="text-sm font-semibold text-cyan-200">1. Подача</p>
                    <p class="mt-2 text-sm text-slate-200">Сотрудник отправляет заявку, и она попадает в буфер `temp_requests`.</p>
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
            <h2 class="text-xl font-semibold text-white">Роли для входа</h2>
            <p class="mt-2 text-sm text-slate-300">Выберите пользователя и откройте нужный кабинет с подставленным `user_id`.</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white">Сотрудники</h2>
                <span class="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">{{ $employees->count() }}</span>
            </div>
            <div class="mt-5 space-y-4">
                @forelse ($employees as $user)
                    <article class="rounded-3xl border border-white/10 bg-slate-900/70 p-4">
                        <p class="font-semibold text-white">{{ $user->full_name }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $user->employee_number }} · {{ $user->phone }}</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a class="rounded-full bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" href="{{ route('employee.requests.index', ['user_id' => $user->id]) }}">Открыть кабинет сотрудника</a>
                        </div>
                    </article>
                @empty
                    <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Активных сотрудников пока нет. Создайте пользователей через сидер или БД.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white">Руководители и администраторы</h2>
                <span class="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">{{ $managers->count() }}</span>
            </div>
            <div class="mt-5 space-y-4">
                @forelse ($managers as $user)
                    <article class="rounded-3xl border border-white/10 bg-slate-900/70 p-4">
                        <p class="font-semibold text-white">{{ $user->full_name }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $user->employee_number }} · {{ $user->role->name }}</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a class="rounded-full bg-amber-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-200" href="{{ route('manager.requests.index', ['user_id' => $user->id]) }}">Открыть кабинет руководителя</a>
                            <a class="rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-cyan-300/50 hover:bg-cyan-400/10" href="{{ route('employee.requests.index', ['user_id' => $user->id]) }}">Посмотреть как сотрудник</a>
                        </div>
                    </article>
                @empty
                    <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Активных руководителей пока нет. В сидере сейчас создаётся только администратор.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
