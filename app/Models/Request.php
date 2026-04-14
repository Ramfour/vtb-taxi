<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
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
        'status',
        'approved_by',
        'temp_request_id',
        'approved_at',
        'exported_at',
        'cancelled_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:8',
            'lon' => 'decimal:8',
            'date_time' => 'datetime',
            'status' => RequestStatus::class,
            'approved_at' => 'datetime',
            'exported_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function tempRequest(): BelongsTo
    {
        return $this->belongsTo(TempRequest::class);
    }
}
