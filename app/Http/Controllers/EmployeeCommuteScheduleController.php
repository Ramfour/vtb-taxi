<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCommuteScheduleRequest;
use App\Models\CommuteSchedule;
use App\Models\CommuteScheduleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EmployeeCommuteScheduleController extends Controller
{
    public function upsert(UpdateCommuteScheduleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $daysMask = 0;
        foreach ($data['days'] as $day) {
            // 1=Mon .. 7=Sun
            $bit = 1 << ((int) $day - 1);
            $daysMask |= $bit;
        }

        DB::transaction(function () use ($user, $data, $daysMask) {
            $schedule = CommuteSchedule::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'days_mask' => $daysMask,
                    'default_time' => $data['default_time'],
                    'default_address_raw' => $data['default_address_raw'] ?: null,
                ],
            );

            $json = (string) ($data['exceptions_json'] ?? '');
            if ($json === '') {
                return;
            }

            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                return;
            }

            $datesInPayload = [];
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $windowDate = (string) ($row['window_date'] ?? '');
                if ($windowDate === '') {
                    continue;
                }
                $datesInPayload[] = $windowDate;

                CommuteScheduleException::query()->updateOrCreate(
                    [
                        'commute_schedule_id' => $schedule->id,
                        'window_date' => $windowDate,
                    ],
                    [
                        'is_skipped' => (bool) ($row['is_skipped'] ?? false),
                        'time_override' => $row['time_override'] ?: null,
                        'address_override_raw' => $row['address_override_raw'] ?: null,
                    ],
                );
            }
        });

        return redirect()
            ->route('employee.requests.index')
            ->with('status', 'Расписание сохранено. Плановые заявки будут формироваться автоматически в 12:00 в день окна.');
    }
}
