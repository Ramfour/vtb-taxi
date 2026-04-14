<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^8\d{10}$/'],
            'default_address' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
