<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Request as FinalRequest;
use App\Models\TempRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogger;

class RequestWorkflowService
{
    public function createTempRequest(User $user, array $attributes): TempRequest
    {
        $dateTime = Carbon::parse($attributes['date_time']);
        $phone = $this->normalizeEmployeePhone($attributes['phone'] ?? '');

        $this->ensureNoDuplicateRequest($user, $dateTime, null);

        $tempRequest = TempRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => $attributes['full_name'],
            'phone' => $phone,
            'address_raw' => $attributes['address_raw'],
            'date_time' => $dateTime,
        ]);

        if (($attributes['address_raw'] ?? null) !== null) {
            $user->forceFill([
                'default_address' => $attributes['address_raw'],
            ])->save();
        }

        AuditLogger::log($user, 'temp_request_created', $tempRequest, [], [
            'date_time' => $tempRequest->date_time?->toDateTimeString(),
            'full_name' => $tempRequest->full_name,
            'phone' => $tempRequest->phone,
            'address_raw' => $tempRequest->address_raw,
        ]);

        return $tempRequest;
    }

    public function reviewTempRequest(TempRequest $tempRequest, User $manager, array $attributes): TempRequest
    {
        $this->expirePastTempRequests();

        if ($tempRequest->status !== RequestStatus::Pending) {
            throw ValidationException::withMessages([
                'temp_request' => 'Можно рассматривать только заявки со статусом «На согласовании».',
            ]);
        }

        if ($tempRequest->date_time->isPast()) {
            $tempRequest->fill([
                'status' => RequestStatus::Expired,
                'cancelled_at' => now(),
            ])->save();

            throw ValidationException::withMessages([
                'temp_request' => 'Время подачи машины уже прошло.',
            ]);
        }

        $oldValues = [
            'status' => $tempRequest->status->value,
            'manager_comment' => $tempRequest->manager_comment,
            'rejection_reason' => $tempRequest->rejection_reason,
        ];

        $tempRequest->fill([
            'status' => $attributes['action'] === 'approve'
                ? RequestStatus::Approved
                : RequestStatus::Rejected,
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
            'manager_comment' => $attributes['manager_comment'] ?? null,
            'rejection_reason' => $attributes['action'] === 'reject'
                ? $attributes['rejection_reason']
                : null,
        ])->save();

        $tempRequest = $tempRequest->refresh();

        AuditLogger::log($manager, 'temp_request_reviewed', $tempRequest, $oldValues, [
            'status' => $tempRequest->status->value,
            'manager_comment' => $tempRequest->manager_comment,
            'rejection_reason' => $tempRequest->rejection_reason,
        ]);

        return $tempRequest;
    }

    public function approveTempRequests(User $manager, array $requestIds = [], string $scope = 'selected', ?string $managerComment = null): int
    {
        $this->expirePastTempRequests();

        return DB::transaction(function () use ($manager, $requestIds, $scope, $managerComment) {
            $query = TempRequest::query()
                ->where('status', RequestStatus::Pending)
                ->where('date_time', '>=', now())
                ->orderBy('date_time');

            if ($scope === 'selected') {
                if ($requestIds === []) {
                    throw ValidationException::withMessages([
                        'request_ids' => 'Выберите хотя бы одну заявку для одобрения.',
                    ]);
                }

                $query->whereKey($requestIds);
            }

            $tempRequests = $query->lockForUpdate()->get();

            if ($tempRequests->isEmpty()) {
                throw ValidationException::withMessages([
                    'request_ids' => 'Нет заявок на согласовании для одобрения.',
                ]);
            }

            foreach ($tempRequests as $tempRequest) {
                $oldValues = [
                    'status' => $tempRequest->status->value,
                    'manager_comment' => $tempRequest->manager_comment,
                    'rejection_reason' => $tempRequest->rejection_reason,
                ];

                $tempRequest->fill([
                    'status' => RequestStatus::Approved,
                    'reviewed_by' => $manager->id,
                    'reviewed_at' => now(),
                    'manager_comment' => $managerComment,
                    'rejection_reason' => null,
                ])->save();

                AuditLogger::log($manager, 'temp_request_bulk_approved', $tempRequest, $oldValues, [
                    'status' => $tempRequest->status->value,
                    'manager_comment' => $tempRequest->manager_comment,
                ]);
            }

            return $tempRequests->count();
        });
    }

    public function cancelTempRequest(TempRequest $tempRequest, User $user, ?string $reason = null): TempRequest
    {
        $this->expirePastTempRequests();

        if ($tempRequest->user_id !== $user->id && ! in_array($user->role->value, [2, 3], true)) {
            throw ValidationException::withMessages([
                'temp_request' => 'You cannot cancel another user request.',
            ]);
        }

        if ($tempRequest->status !== RequestStatus::Pending) {
            throw ValidationException::withMessages([
                'temp_request' => 'Only pending requests can be cancelled.',
            ]);
        }

        $tempRequest->fill([
            'status' => RequestStatus::Cancelled,
            'cancelled_at' => now(),
            'manager_comment' => $reason,
        ])->save();

        AuditLogger::log($user, 'temp_request_cancelled', $tempRequest, [
            'status' => RequestStatus::Pending->value,
        ], [
            'status' => $tempRequest->status->value,
            'manager_comment' => $tempRequest->manager_comment,
        ]);

        return $tempRequest->refresh();
    }

    public function finalizeApprovedRequests(User $manager, ?string $from = null, ?string $to = null): Collection
    {
        $this->expirePastTempRequests();

        return DB::transaction(function () use ($manager, $from, $to) {
            $query = TempRequest::query()
                ->where('status', RequestStatus::Approved)
                ->whereDoesntHave('finalRequest')
                ->orderBy('date_time');

            if ($from) {
                $query->where('date_time', '>=', Carbon::parse($from));
            }

            if ($to) {
                $query->where('date_time', '<=', Carbon::parse($to));
            }

            $tempRequests = $query->lockForUpdate()->get();

            $finalRequests = $tempRequests->map(function (TempRequest $tempRequest) use ($manager) {
                $this->ensureNoDuplicateRequest($tempRequest->user, $tempRequest->date_time, $tempRequest->id);

                $finalRequest = FinalRequest::query()->create([
                    'user_id' => $tempRequest->user_id,
                    'full_name' => $tempRequest->full_name,
                    'phone' => $tempRequest->phone,
                    'address_raw' => $tempRequest->address_raw,
                    'address_norm' => $tempRequest->address_norm,
                    'lat' => $tempRequest->lat,
                    'lon' => $tempRequest->lon,
                    'date_time' => $tempRequest->date_time,
                    'status' => RequestStatus::Approved,
                    'approved_by' => $manager->id,
                    'temp_request_id' => $tempRequest->id,
                    'approved_at' => $tempRequest->reviewed_at ?? now(),
                ]);

                AuditLogger::log($manager, 'final_request_created', $finalRequest, [], [
                    'temp_request_id' => $tempRequest->id,
                    'date_time' => $finalRequest->date_time?->toDateTimeString(),
                    'full_name' => $finalRequest->full_name,
                    'phone' => $finalRequest->phone,
                    'address_raw' => $finalRequest->address_raw,
                ]);

                AuditLogger::log($manager, 'temp_request_finalized', $tempRequest, [
                    'status' => $tempRequest->status->value,
                ], [
                    'final_request_id' => $finalRequest->id,
                ]);

                $tempRequest->delete();

                return $finalRequest;
            });

            return new Collection($finalRequests->all());
        });
    }

    public function exportFinalRequests(?string $from = null, ?string $to = null, ?int $limit = null): \Illuminate\Support\Collection
    {
        $query = FinalRequest::query()->orderBy('date_time');

        if ($from) {
            $query->where('date_time', '>=', Carbon::parse($from));
        }

        if ($to) {
            $query->where('date_time', '<=', Carbon::parse($to));
        }

        if (! $from && ! $to) {
            $range = $this->todayNightRange();
            $query->whereBetween('date_time', [$range['from'], $range['to']]);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(function (FinalRequest $request) {
            $nightRange = $this->todayNightRange();
            $isOutsideNight = $request->date_time
                ? ! $request->date_time->between($nightRange['from'], $nightRange['to'])
                : false;

            return [
                'id' => $request->id,
                'date_time' => $request->date_time?->format('d.m.Y H:i') ?? '',
                'date_time_raw' => $request->date_time?->format('Y-m-d\\TH:i') ?? '',
                'full_name' => $this->formatFullNameForExport($request->full_name),
                'address' => $this->formatAddressForExport($request->address_norm ?? $request->address_raw),
                'address_raw' => $request->address_raw ?? '',
                'phone' => $request->phone,
                'is_outside_night' => $isOutsideNight,
            ];
        });
    }

    public function previewFinalRequests(?string $from = null, ?string $to = null, int $perPage = 10)
    {
        $query = FinalRequest::query()->orderBy('date_time');

        if ($from) {
            $query->where('date_time', '>=', Carbon::parse($from));
        }

        if ($to) {
            $query->where('date_time', '<=', Carbon::parse($to));
        }

        if (! $from && ! $to) {
            $range = $this->todayNightRange();
            $query->whereBetween('date_time', [$range['from'], $range['to']]);
        }

        return $query->paginate($perPage, ['*'], 'export_page')->withQueryString();
    }

    public function countFinalRequests(?string $from = null, ?string $to = null): int
    {
        $query = FinalRequest::query();

        if ($from) {
            $query->where('date_time', '>=', Carbon::parse($from));
        }

        if ($to) {
            $query->where('date_time', '<=', Carbon::parse($to));
        }

        if (! $from && ! $to) {
            $range = $this->todayNightRange();
            $query->whereBetween('date_time', [$range['from'], $range['to']]);
        }

        return $query->count();
    }

    public function employeeDashboard(User $user): array
    {
        $this->expirePastTempRequests();

        return [
            'temp_requests' => TempRequest::query()
                ->where('user_id', $user->id)
                ->latest('date_time')
                ->get(),
            'requests' => FinalRequest::query()
                ->where('user_id', $user->id)
                ->latest('date_time')
                ->get(),
        ];
    }

    public function managerDashboard(?string $statusFilter = null, ?string $sort = null): array
    {
        $this->expirePastTempRequests();

        $statusFilter = $statusFilter ?: 'all';
        $sort = $sort ?: 'asc';

        $statusMap = [
            'pending' => RequestStatus::Pending,
            'approved' => RequestStatus::Approved,
            'rejected' => RequestStatus::Rejected,
            'cancelled' => RequestStatus::Cancelled,
            'expired' => RequestStatus::Expired,
        ];

        if (! isset($statusMap[$statusFilter]) && $statusFilter !== 'all') {
            $statusFilter = 'all';
        }

        if (! in_array($sort, ['asc', 'desc'], true)) {
            $sort = 'asc';
        }

        $bufferQuery = TempRequest::query();

        if ($statusFilter !== 'all') {
            $bufferQuery->where('status', $statusMap[$statusFilter]);
        }

        if ($sort === 'asc') {
            $bufferQuery->orderBy('date_time')->orderBy('id');
        } else {
            $bufferQuery->orderByDesc('date_time')->orderByDesc('id');
        }

        $bufferVisibleTotal = (clone $bufferQuery)->count();
        $bufferTotal = TempRequest::query()->count();

        return [
            'buffer' => $bufferQuery->paginate(12)->withQueryString(),
            'buffer_total' => $bufferTotal,
            'buffer_visible_total' => $bufferVisibleTotal,
            'buffer_status' => $statusFilter,
            'buffer_sort' => $sort,
            'pending_total' => TempRequest::query()
                ->where('status', RequestStatus::Pending)
                ->count(),
            'approved_total' => TempRequest::query()
                ->where('status', RequestStatus::Approved)
                ->count(),
            'finalized' => FinalRequest::query()
                ->latest('date_time')
                ->limit(50)
                ->get(),
        ];
    }

    private function ensureNoDuplicateRequest(User $user, Carbon $dateTime, ?int $exceptTempRequestId): void
    {
        $tempDuplicateExists = TempRequest::query()
            ->where('user_id', $user->id)
            ->where('date_time', $dateTime)
            ->when($exceptTempRequestId, fn ($query) => $query->whereKeyNot($exceptTempRequestId))
            ->whereIn('status', [
                RequestStatus::Pending->value,
                RequestStatus::Approved->value,
            ])
            ->exists();

        $finalDuplicateExists = FinalRequest::query()
            ->where('user_id', $user->id)
            ->where('date_time', $dateTime)
            ->exists();

        if ($tempDuplicateExists || $finalDuplicateExists) {
            throw ValidationException::withMessages([
                'date_time' => 'A request for this user and time already exists.',
            ]);
        }
    }

    private function expirePastTempRequests(): void
    {
        TempRequest::query()
            ->where('status', RequestStatus::Pending)
            ->where('date_time', '<', now())
            ->update([
                'status' => RequestStatus::Expired,
                'cancelled_at' => now(),
            ]);
    }

    private function formatAddressForExport(?string $address): string
    {
        $address = trim((string) $address);

        if ($address === '') {
            return '';
        }

        $address = preg_replace('/^Россия,\s*/u', '', $address);
        $address = preg_replace('/\bг\.\s*/u', '', $address);

        return preg_replace('/\s{2,}/u', ' ', $address) ?? $address;
    }

    private function formatFullNameForExport(?string $fullName): string
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) === 3 && preg_match('/(ич|вич|ьмич|оглы|кызы|овна|евна|ична)$/u', $parts[1])) {
            return implode(' ', [$parts[2], $parts[0], $parts[1]]);
        }

        return $fullName;
    }

    private function todayNightRange(): array
    {
        $today = now();
        $from = $today->copy()->setTime(22, 0);

        if ($today->hour < 6) {
            $from = $from->subDay();
        }

        $to = $from->copy()->addHours(8);

        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    private function normalizeEmployeePhone(string $phone): string
    {
        $value = trim($phone);

        if ($value === '') {
            return $value;
        }

        if (str_starts_with($value, '+7')) {
            return '8'.substr($value, 2);
        }

        return $value;
    }
}
