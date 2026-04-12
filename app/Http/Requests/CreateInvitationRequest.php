<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [
            UserRole::Manager,
            UserRole::Admin,
        ], true);
    }

    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'digits:8'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'role' => ['nullable', Rule::in(['employee', 'manager'])],
        ];
    }
}
