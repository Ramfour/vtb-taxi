<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\Request as FinalRequest;
use App\Models\TempRequest;
use Illuminate\Console\Command;

class TaxiCleanupCommand extends Command
{
    protected $signature = 'taxi:cleanup {--dry-run : Show what would be deleted, but do not delete}';
    protected $description = 'Cleanup old taxi data according to retention policy';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $retention = (array) config('taxi.retention', []);
        $tempSoftDeletedDays = (int) ($retention['temp_soft_deleted_days'] ?? 7);
        $tempReviewedDays = (int) ($retention['temp_reviewed_days'] ?? 7);
        $finalAfterExportDays = (int) ($retention['final_after_export_days'] ?? 5);
        $finalSoftDeletedDays = (int) ($retention['final_soft_deleted_days'] ?? 5);
        $auditDays = (int) ($retention['audit_days'] ?? 90);

        $this->info('Taxi cleanup started'.($dryRun ? ' (dry-run)' : '').'.');
        $this->line('Retention (days): temp soft-deleted='.$tempSoftDeletedDays.', temp reviewed='.$tempReviewedDays.', final after export='.$finalAfterExportDays.', final soft-deleted='.$finalSoftDeletedDays.', audit='.$auditDays);

        // 1) Temp requests: finalized buffer entries are soft-deleted; keep 7 days then purge permanently.
        $tempSoftCutoff = now()->subDays($tempSoftDeletedDays);
        $tempSoftQuery = TempRequest::query()
            ->onlyTrashed()
            ->where('deleted_at', '<', $tempSoftCutoff);

        $tempSoftCount = (clone $tempSoftQuery)->count();
        $this->line("TempRequest: to force-delete (soft-deleted older than {$tempSoftDeletedDays}d): {$tempSoftCount}");
        if (! $dryRun && $tempSoftCount > 0) {
            $tempSoftQuery->forceDelete();
        }

        // 2) Temp requests: reviewed/cancelled/expired that are not pending can be purged after 7 days.
        // We intentionally do NOT auto-delete Approved (not finalized) to avoid accidental data loss.
        $tempReviewedCutoff = now()->subDays($tempReviewedDays);
        $tempReviewedQuery = TempRequest::query()
            ->whereNull('deleted_at')
            ->whereIn('status', [
                RequestStatus::Rejected->value,
                RequestStatus::Cancelled->value,
                RequestStatus::Expired->value,
            ])
            ->where(function ($q) use ($tempReviewedCutoff) {
                $q->where('reviewed_at', '<', $tempReviewedCutoff)
                    ->orWhere('cancelled_at', '<', $tempReviewedCutoff)
                    ->orWhere(function ($q2) use ($tempReviewedCutoff) {
                        $q2->whereNull('reviewed_at')
                            ->whereNull('cancelled_at')
                            ->where('updated_at', '<', $tempReviewedCutoff);
                    });
            });

        $tempReviewedCount = (clone $tempReviewedQuery)->count();
        $this->line("TempRequest: to force-delete (rejected/cancelled/expired older than {$tempReviewedDays}d): {$tempReviewedCount}");
        if (! $dryRun && $tempReviewedCount > 0) {
            $tempReviewedQuery->forceDelete();
        }

        // 3) Final requests: delete 5 days after CSV export.
        $finalExportCutoff = now()->subDays($finalAfterExportDays);
        $finalExportQuery = FinalRequest::query()
            ->whereNotNull('exported_at')
            ->where('exported_at', '<', $finalExportCutoff);

        $finalExportCount = (clone $finalExportQuery)->count();
        $this->line("FinalRequest: to force-delete (exported older than {$finalAfterExportDays}d): {$finalExportCount}");
        if (! $dryRun && $finalExportCount > 0) {
            $finalExportQuery->forceDelete();
        }

        // 4) Final requests: if someone manually soft-deleted entries, purge them after 5 days.
        $finalSoftCutoff = now()->subDays($finalSoftDeletedDays);
        $finalSoftQuery = FinalRequest::query()
            ->onlyTrashed()
            ->where('deleted_at', '<', $finalSoftCutoff);

        $finalSoftCount = (clone $finalSoftQuery)->count();
        $this->line("FinalRequest: to force-delete (soft-deleted older than {$finalSoftDeletedDays}d): {$finalSoftCount}");
        if (! $dryRun && $finalSoftCount > 0) {
            $finalSoftQuery->forceDelete();
        }

        // 5) Audit logs: hard-delete old entries.
        $auditCutoff = now()->subDays($auditDays);
        $auditQuery = AuditLog::query()->where('created_at', '<', $auditCutoff);
        $auditCount = (clone $auditQuery)->count();
        $this->line("AuditLog: to delete (older than {$auditDays}d): {$auditCount}");
        if (! $dryRun && $auditCount > 0) {
            $auditQuery->delete();
        }

        $this->info('Taxi cleanup finished.');

        return self::SUCCESS;
    }
}

