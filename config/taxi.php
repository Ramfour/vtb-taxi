<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retention Policy (days)
    |--------------------------------------------------------------------------
    |
    | These values control automatic cleanup of accumulated data.
    | Set them in .env if you need to tune for production.
    |
    */
    'retention' => [
        // Soft-deleted temp requests (finalized buffer) are permanently removed after this many days.
        'temp_soft_deleted_days' => (int) env('TAXI_TEMP_SOFT_DELETED_DAYS', 7),

        // Non-pending temp requests (rejected/cancelled/expired) are permanently removed after this many days.
        'temp_reviewed_days' => (int) env('TAXI_TEMP_REVIEWED_DAYS', 7),

        // Final requests are permanently removed N days after they were exported to CSV.
        'final_after_export_days' => (int) env('TAXI_FINAL_AFTER_EXPORT_DAYS', 5),

        // Any soft-deleted final requests are permanently removed after this many days.
        'final_soft_deleted_days' => (int) env('TAXI_FINAL_SOFT_DELETED_DAYS', 5),

        // Audit log retention.
        'audit_days' => (int) env('TAXI_AUDIT_DAYS', 90),
    ],
];

