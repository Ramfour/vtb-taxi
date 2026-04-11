<x-layouts.portal
    title="Кабинет сотрудника"
    heading="Кабинет сотрудника"
    subheading="Здесь можно подать новую заявку, следить за буфером согласования и смотреть финальные записи."
    :current-user="$currentUser"
>
    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <h2 class="text-xl font-semibold text-white">Новая заявка</h2>
            <form method="POST" action="{{ route('employee.requests.store') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-200">ФИО</span>
                    <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="text" name="full_name" value="{{ old('full_name', $currentUser->full_name) }}" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-200">Телефон</span>
                    <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="text" name="phone" value="{{ old('phone', $currentUser->phone) }}" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-200">Адрес назначения</span>
                    <textarea class="min-h-28 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" name="address_raw" required>{{ old('address_raw', $currentUser->default_address) }}</textarea>
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-slate-200">Дата и время поездки</span>
                    <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="datetime-local" name="date_time" value="{{ old('date_time') }}" required>
                </label>
                <button class="w-full rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" type="submit">Отправить заявку на согласование</button>
            </form>
        </section>

        <section class="space-y-6">
            <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Буфер согласования</h2>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-slate-300">{{ $dashboard['temp_requests']->count() }}</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($dashboard['temp_requests'] as $request)
                        <article class="rounded-3xl border border-white/10 bg-slate-900/70 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="font-semibold text-white">{{ $request->full_name }}</p>
                                    <p class="mt-1 text-sm text-slate-400">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</p>
                                </div>
                                <x-status-badge :status="$request->status" />
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-200">{{ $request->address_norm ?? $request->address_raw }}</p>
                            @if ($request->rejection_reason)
                                <p class="mt-3 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-100">{{ $request->rejection_reason }}</p>
                            @endif
                            @if ($request->status->name === 'Pending')
                                <form method="POST" action="{{ route('employee.requests.cancel', $request) }}" class="mt-4 flex gap-3">
                                    @csrf
                                    @method('PATCH')
                                    <input class="flex-1 rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-2 text-sm text-white outline-none transition focus:border-rose-300/60" type="text" name="reason" placeholder="Причина отмены, если нужна">
                                    <button class="rounded-2xl border border-rose-300/30 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/20" type="submit">Отменить</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Пока нет заявок в буфере.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layouts.portal>
