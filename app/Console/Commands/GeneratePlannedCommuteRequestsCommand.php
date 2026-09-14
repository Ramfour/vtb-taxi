<?php

namespace App\Console\Commands;

use App\Models\CommuteSchedule;
use App\Models\CommuteScheduleException;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GeneratePlannedCommuteRequestsCommand extends Command
{
    protected $signature = 'taxi:generate-planned-requests {--date= : Window date D in YYYY-MM-DD (defaults to today)} {--dry-run : Show what would be created}';
    protected $description = 'Generate today window (D 22:00–06:00) temp requests from employee commute schedules';

    public function __construct(
        private readonly RequestWorkflowService $workflow,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOpt = (string) ($this->option('date') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        $windowDate = $dateOpt !== ''
            ? Carbon::parse($dateOpt)->startOfDay()
            : now()->startOfDay();

        $this->info('Planned commute generation started for window date D='.$windowDate->toDateString().($dryRun ? ' (dry-run)' : '').'.');

        $isoWeekday = (int) $windowDate->isoWeekday(); // 1=Mon..7=Sun

        $schedules = CommuteSchedule::query()
            ->where('is_active', true)
            ->with(['user.latestAddress'])
            ->get();

        $created = 0;
        $skipped = 0;
        $ignored = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->isEnabledForIsoWeekday($isoWeekday)) {
                $ignored++;
                continue;
            }

            /** @var User $user */
            $user = $schedule->user;
            if (! $user || ! $user->is_active) {
                $ignored++;
                continue;
            }

            $exception = CommuteScheduleException::query()
                ->where('commute_schedule_id', $schedule->id)
                ->where('window_date', $windowDate->toDateString())
                ->first();

            if ($exception?->is_skipped) {
                $skipped++;
                continue;
            }

            $time = $exception?->time_override ?: $schedule->default_time;
            if (! $time) {
                $ignored++;
                continue;
            }

            $timeStr = Carbon::parse($time)->format('H:i');
            if (! $this->isTimeInNightWindow($timeStr)) {
                $this->warn("Skip user {$user->id} ({$user->employee_number}): time {$timeStr} is outside 22:00–06:00.");
                $ignored++;
                continue;
            }

            $address = trim((string) ($exception?->address_override_raw ?: $schedule->default_address_raw ?: $user->latestAddress?->address));
            if ($address === '') {
                $this->warn("Skip user {$user->id} ({$user->employee_number}): no address.");
                $ignored++;
                continue;
            }

            $dateTime = $this->resolveDateTimeForWindow($windowDate, $timeStr);

            if ($dryRun) {
                $this->line("Would create temp request: user={$user->employee_number}, window_date={$windowDate->toDateString()}, date_time={$dateTime->toDateTimeString()}, time={$timeStr}");
                $created++;
                continue;
            }

            try {
                DB::transaction(function () use ($user, $windowDate, $dateTime, $address, &$created) {
                    $temp = $this->workflow->createTempRequest($user, [
                        'full_name' => $user->full_name,
                        'phone' => $user->phone,
                        'address_raw' => $address,
                        'date_time' => $dateTime->toDateTimeString(),
                    ]);

                    // Mark as planned + attach window_date for filtering.
                    $temp->fill([
                        'is_planned' => true,
                        'window_date' => $windowDate->toDateString(),
                    ])->save();

                    $created++;
                });
            } catch (\Throwable $e) {
                $this->warn("Failed for user {$user->employee_number}: ".$e->getMessage());
            }
        }

        $this->info("Finished. created={$created}, skipped={$skipped}, ignored={$ignored}");

        return self::SUCCESS;
    }

    private function isTimeInNightWindow(string $hhmm): bool
    {
        // Allowed: 22:00–23:59 OR 00:00–06:00.
        return ($hhmm >= '22:00' && $hhmm <= '23:59') || ($hhmm >= '00:00' && $hhmm <= '06:00');
    }

    private function resolveDateTimeForWindow(Carbon $windowDate, string $hhmm): Carbon
    {
        // If time is 00:00–06:00, it belongs to window date D but is calendar day D+1.
        $base = $windowDate->copy();
        if ($hhmm >= '00:00' && $hhmm <= '06:00') {
            $base = $base->addDay();
        }

        [$h, $m] = array_map('intval', explode(':', $hhmm));
        return $base->copy()->setTime($h, $m);
    }
}

