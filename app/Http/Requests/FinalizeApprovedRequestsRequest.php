<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeApprovedRequestsRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
