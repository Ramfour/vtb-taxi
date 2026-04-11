@props(['status'])

@php
    $map = [
        'Pending' => 'border-amber-400/30 bg-amber-500/10 text-amber-100',
        'Approved' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100',
        'Rejected' => 'border-rose-400/30 bg-rose-500/10 text-rose-100',
        'Cancelled' => 'border-slate-400/30 bg-slate-500/10 text-slate-100',
    ];
@endphp

<span {{ $attributes->class(['inline-flex rounded-full border px-3 py-1 text-xs font-semibold tracking-wide', $map[$status->name] ?? 'border-white/10 bg-white/10 text-white']) }}>
    {{ $status->name }}
</span>
