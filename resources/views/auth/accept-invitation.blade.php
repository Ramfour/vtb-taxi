<x-layouts.portal
    title="Активация приглашения"
    heading="Завершение регистрации"
    subheading="Приглашение создано для табельного номера {{ $invitation->employee_number }}. Заполните профиль и задайте пароль для входа."
>
    <div class="mx-auto max-w-2xl rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
        <form method="POST" action="{{ route('invitation.accept.store', $invitation->token) }}" class="grid gap-5 md:grid-cols-2">
            @csrf
            <label class="block md:col-span-2">
                <span class="mb-2 block text-sm font-medium text-slate-200">ФИО</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="text" name="full_name" value="{{ old('full_name') }}" required>
            </label>
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-200">Телефон</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="text" name="phone" value="{{ old('phone') }}" placeholder="89131234567" required>
            </label>
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-200">Email</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="email" name="email" value="{{ old('email') }}">
            </label>
            <label class="block md:col-span-2">
                <span class="mb-2 block text-sm font-medium text-slate-200">Адрес по умолчанию</span>
                <textarea class="min-h-24 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" name="default_address">{{ old('default_address') }}</textarea>
            </label>
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-200">Пароль</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="password" name="password" required>
            </label>
            <label class="block">
                <span class="mb-2 block text-sm font-medium text-slate-200">Подтверждение пароля</span>
                <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="password" name="password_confirmation" required>
            </label>
            <button class="md:col-span-2 rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" type="submit">Завершить регистрацию</button>
        </form>
    </div>
</x-layouts.portal>
