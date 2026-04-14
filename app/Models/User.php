<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'employee_number',
        'telegram_id',
        'full_name',
        'phone',
        'password',
        'role',
        'is_active',
        'do_not_disturb_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'string',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'do_not_disturb_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function tempRequests(): HasMany
    {
        return $this->hasMany(TempRequest::class);
    }

    public function createdInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'created_by');
    }

    public function usedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'used_by');
    }

    public function reviewedTempRequests(): HasMany
    {
        return $this->hasMany(TempRequest::class, 'reviewed_by');
    }

    public function approvedRequests(): HasMany
    {
        return $this->hasMany(Request::class, 'approved_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function latestAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->latestOfMany();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function employees(Builder $query): void
    {
        $query->where('role', UserRole::Employee->value);
    }

    #[Scope]
    protected function managers(Builder $query): void
    {
        $query->whereIn('role', [UserRole::Manager->value, UserRole::Admin->value]);
    }
}
