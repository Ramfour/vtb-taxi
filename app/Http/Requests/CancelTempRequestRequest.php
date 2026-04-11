<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CancelTempRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [
            UserRole::Employee,
            UserRole::Manager,
            UserRole::Admin,
        ], true);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
