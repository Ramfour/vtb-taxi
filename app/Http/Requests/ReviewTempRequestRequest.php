<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTempRequestRequest extends FormRequest
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
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'manager_comment' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ];
    }
}
