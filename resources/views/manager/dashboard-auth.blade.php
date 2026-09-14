<x-layouts.portal
    title="Кабинет руководителя"
    heading="Кабинет руководителя"
    subheading="Буфер заявок, решения по согласованию и перенос одобренных записей в основную таблицу."
    :current-user="$currentUser"
>
    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-white">Буфер temp_requests</h2>
                    <p class="mt-2 text-sm text-slate-300">Здесь отображаются все временные заявки до финального переноса.</p>
                </div>
                <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-slate-300">{{ $dashboard['buffer']->count() }}</span>
            </div>
            <div class="mt-6 space-y-4">
                @forelse ($dashboard['buffer'] as $request)
                    <article class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="font-semibold text-white">{{ $request->full_name }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ $request->user?->employee_number }} · {{ $request->phone }} · {{ $request->date_time?->format('d.m.Y H:i') }}</p>
                            </div>
                            <x-status-badge :status="$request->status" />
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-200">{{ $request->address_norm ?? $request->address_raw }}</p>
                        @if ($request->status->name === 'Pending')
                            <div class="mt-5 grid gap-4 xl:grid-cols-2">
                                <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="space-y-3 rounded-3xl border border-emerald-400/15 bg-emerald-500/5 p-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="approve">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-emerald-100">Комментарий руководителя</span>
                                        <textarea class="min-h-24 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-300/60" name="manager_comment"></textarea>
                                    </label>
                                    <button class="w-full rounded-2xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300" type="submit">Одобрить</button>
                                </form>
                                <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="space-y-3 rounded-3xl border border-rose-400/15 bg-rose-500/5 p-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="reject">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-rose-100">Причина отклонения</span>
                                        <textarea class="min-h-24 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-rose-300/60" name="rejection_reason" required></textarea>
                                    </label>
                                    <button class="w-full rounded-2xl border border-rose-300/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/20" type="submit">Отклонить</button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Буфер пуст.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
                <h2 class="text-xl font-semibold text-white">Приглашение сотрудника</h2>
                <form method="POST" action="{{ route('manager.invitations.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">Табельный номер</span>
                            <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="text" name="employee_number" placeholder="71234567" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">Срок действия, дней</span>
                            <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/60" type="number" name="expires_in_days" value="7" min="1" max="30">
                        </label>
                    </div>
                    <button class="w-full rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" type="submit">Создать приглашение</button>
                </form>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
                <h2 class="text-xl font-semibold text-white">Финализация одобренных заявок</h2>
                <form method="POST" action="{{ route('manager.requests.finalize') }}" class="mt-6 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">С</span>
                            <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/60" type="datetime-local" name="from">
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-slate-200">По</span>
                            <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300/60" type="datetime-local" name="to">
                        </label>
                    </div>
                    <button class="w-full rounded-2xl bg-amber-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-200" type="submit">Перенести одобренные заявки в requests</button>
                </form>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Последние приглашения</h2>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-slate-300">{{ $invitations->count() }}</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($invitations as $invitation)
                        <article class="rounded-3xl border border-white/10 bg-slate-900/70 p-4">
                            <p class="font-semibold text-white">{{ $invitation->employee_number }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ $invitation->is_used ? 'Использовано' : 'Ожидает активации' }} · до {{ optional($invitation->expires_at)->format('d.m.Y H:i') }}</p>
                            <p class="mt-3 break-all rounded-2xl border border-cyan-400/20 bg-cyan-500/10 px-3 py-2 text-xs text-cyan-100">{{ route('invitation.accept.show', $invitation->token) }}</p>
                        </article>
                    @empty
                        <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Приглашений пока нет.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layouts.portal>
