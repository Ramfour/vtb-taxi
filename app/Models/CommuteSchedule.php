<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommuteSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'is_active',
        'days_mask',
        'default_time',
        'default_address_raw',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'days_mask' => 'int',
            'default_time' => 'datetime:H:i:s',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(CommuteScheduleException::class);
    }

    public function isEnabledForIsoWeekday(int $isoWeekday): bool
    {
        // ISO weekday: 1=Mon ... 7=Sun.
        $bit = 1 << ($isoWeekday - 1);
        return (($this->days_mask ?? 0) & $bit) !== 0;
    }
}

