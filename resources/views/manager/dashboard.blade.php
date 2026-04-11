<x-layouts.app
    title="Кабинет руководителя"
    heading="Кабинет руководителя"
    subheading="Буфер заявок, решения по согласованию и перенос одобренных записей в основную таблицу."
    :current-user="$currentUser"
>
    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <section class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-white">Буфер `temp_requests`</h2>
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
                        @if ($request->manager_comment)
                            <p class="mt-3 rounded-2xl border border-cyan-400/20 bg-cyan-500/10 px-3 py-2 text-sm text-cyan-100">{{ $request->manager_comment }}</p>
                        @endif
                        @if ($request->rejection_reason)
                            <p class="mt-3 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-sm text-rose-100">{{ $request->rejection_reason }}</p>
                        @endif
                        @if ($request->status->name === 'Pending')
                            <div class="mt-5 grid gap-4 xl:grid-cols-2">
                                <form method="POST" action="{{ route('manager.requests.review', $request) }}" class="space-y-3 rounded-3xl border border-emerald-400/15 bg-emerald-500/5 p-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="user_id" value="{{ $currentUser->id }}">
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
                                    <input type="hidden" name="user_id" value="{{ $currentUser->id }}">
                                    <input type="hidden" name="action" value="reject">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-rose-100">Причина отклонения</span>
                                        <textarea class="min-h-24 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-rose-300/60" name="rejection_reason" required></textarea>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-slate-300">Комментарий</span>
                                        <input class="w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition focus:border-rose-300/60" type="text" name="manager_comment">
                                    </label>
                                    <button class="w-full rounded-2xl border border-rose-300/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/20" type="submit">Отклонить</button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Буфер пуст. Новые заявки появятся здесь после отправки сотрудниками.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 backdrop-blur">
                <h2 class="text-xl font-semibold text-white">Финализация одобренных заявок</h2>
                <p class="mt-2 text-sm text-slate-300">Кнопка переносит все `approved` записи из буфера в основную таблицу `requests`.</p>
                <form method="POST" action="{{ route('manager.requests.finalize') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $currentUser->id }}">
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
                    <h2 class="text-xl font-semibold text-white">Последние финальные заявки</h2>
                    <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-slate-300">{{ $dashboard['finalized']->count() }}</span>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse ($dashboard['finalized'] as $request)
                        <article class="rounded-3xl border border-white/10 bg-slate-900/70 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="font-semibold text-white">{{ $request->full_name }}</p>
                                    <p class="mt-1 text-sm text-slate-400">{{ $request->date_time?->format('d.m.Y H:i') }} · {{ $request->phone }}</p>
                                </div>
                                <x-status-badge :status="$request->status" />
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-200">{{ $request->address_norm ?? $request->address_raw }}</p>
                        </article>
                    @empty
                        <p class="rounded-3xl border border-dashed border-white/15 p-6 text-sm text-slate-400">После финализации последние записи будут показаны в этом блоке.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
