@props(['status'])

@php
    $classMap = [
        'Pending' => 'status-pending',
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        'Cancelled' => 'status-cancelled',
        'Expired' => 'status-expired',
    ];

    $labelMap = [
        'Pending' => 'На согласовании',
        'Approved' => 'Одобрено',
        'Rejected' => 'Отклонено',
        'Cancelled' => 'Отменено',
        'Expired' => 'Истекла',
    ];
@endphp

<span {{ $attributes->class(['status-badge', $classMap[$status->name] ?? null]) }}>
    {{ $labelMap[$status->name] ?? $status->name }}
</span>
