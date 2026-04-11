<x-layouts.portal
    title="Вход в систему"
    heading="Вход по табельному номеру"
    subheading="Руководители и сотрудники входят по табельному номеру ВТБ и паролю. При первом доступе сотрудник завершает регистрацию по приглашению."
>
    <div class="mx-auto max-w-xl rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-200">Табельный номер</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="text" name="employee_number" value="{{ old('employee_number') }}" placeholder="70320699" required autofocus>
            </label>
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-200">Пароль</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="password" name="password" required>
            </label>
            <button class="w-full rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" type="submit">Войти</button>
        </form>
    </div>
</x-layouts.portal>
