<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommuteScheduleException extends Model
{
    protected $fillable = [
        'commute_schedule_id',
        'window_date',
        'is_skipped',
        'time_override',
        'address_override_raw',
    ];

    protected function casts(): array
    {
        return [
            'window_date' => 'date',
            'is_skipped' => 'bool',
            'time_override' => 'datetime:H:i:s',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CommuteSchedule::class, 'commute_schedule_id');
    }
}

