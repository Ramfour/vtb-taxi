<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TempRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'address_raw',
        'address_norm',
        'lat',
        'lon',
        'date_time',
        'is_planned',
        'window_date',
        'status',
        'reviewed_by',
        'reviewed_at',
        'cancelled_at',
        'manager_comment',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:8',
            'lon' => 'decimal:8',
            'date_time' => 'datetime',
            'is_planned' => 'bool',
            'window_date' => 'date',
            'status' => RequestStatus::class,
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function finalRequest(): HasOne
    {
        return $this->hasOne(Request::class);
    }
}
