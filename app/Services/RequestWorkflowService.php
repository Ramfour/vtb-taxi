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

class RequestWorkflowService
{
    public function createTempRequest(User $user, array $attributes): TempRequest
    {
        $dateTime = Carbon::parse($attributes['date_time']);

        $this->ensureNoDuplicateRequest($user, $dateTime, null);

        return TempRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => $attributes['full_name'],
            'phone' => $attributes['phone'],
            'address_raw' => $attributes['address_raw'],
            'date_time' => $dateTime,
        ]);
    }

    public function reviewTempRequest(TempRequest $tempRequest, User $manager, array $attributes): TempRequest
    {
        if ($tempRequest->status !== RequestStatus::Pending) {
            throw ValidationException::withMessages([
                'temp_request' => 'Only pending requests can be reviewed.',
            ]);
        }

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

        return $tempRequest->refresh();
    }

    public function approveTempRequests(User $manager, array $requestIds = [], string $scope = 'selected', ?string $managerComment = null): int
    {
        return DB::transaction(function () use ($manager, $requestIds, $scope, $managerComment) {
            $query = TempRequest::query()
                ->where('status', RequestStatus::Pending)
                ->orderBy('date_time');

            if ($scope === 'selected') {
                if ($requestIds === []) {
                    throw ValidationException::withMessages([
                        'request_ids' => 'Select at least one request to approve.',
                    ]);
                }

                $query->whereKey($requestIds);
            }

            $tempRequests = $query->lockForUpdate()->get();

            if ($tempRequests->isEmpty()) {
                throw ValidationException::withMessages([
                    'request_ids' => 'No pending requests were found for approval.',
                ]);
            }

            foreach ($tempRequests as $tempRequest) {
                $tempRequest->fill([
                    'status' => RequestStatus::Approved,
                    'reviewed_by' => $manager->id,
                    'reviewed_at' => now(),
                    'manager_comment' => $managerComment,
                    'rejection_reason' => null,
                ])->save();
            }

            return $tempRequests->count();
        });
    }

    public function cancelTempRequest(TempRequest $tempRequest, User $user, ?string $reason = null): TempRequest
    {
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

        return $tempRequest->refresh();
    }

    public function finalizeApprovedRequests(User $manager, ?string $from = null, ?string $to = null): Collection
    {
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

                $tempRequest->delete();

                return $finalRequest;
            });

            return new Collection($finalRequests->all());
        });
    }

    public function employeeDashboard(User $user): array
    {
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

    public function managerDashboard(): array
    {
        return [
            'buffer' => TempRequest::query()
                ->latest('date_time')
                ->get(),
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
}
