<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class BulkApproveTempRequestsRequest extends FormRequest
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
            'request_ids' => ['nullable', 'array'],
            'request_ids.*' => ['integer', 'distinct', 'exists:temp_requests,id'],
            'approve_scope' => ['nullable', 'in:selected,all_pending'],
            'manager_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
