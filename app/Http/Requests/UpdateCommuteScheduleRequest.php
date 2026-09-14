<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommuteScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', Rule::in([1, 2, 3, 4, 5, 6, 7])],
            // HH:MM (we validate the allowed window in withValidator)
            'default_time' => ['required', 'date_format:H:i'],
            'default_address_raw' => ['nullable', 'string', 'max:500'],
            // JSON array of exceptions for upcoming dates: [{window_date:"YYYY-MM-DD",is_skipped:bool,time_override:"HH:MM"|null,address_override_raw:string|null}]
            'exceptions_json' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $time = (string) $this->input('default_time', '');
            if ($time === '') {
                return;
            }

            // Allowed: 22:00–23:59 OR 00:00–06:00 (belongs to window D).
            $ok = ($time >= '22:00' && $time <= '23:59') || ($time >= '00:00' && $time <= '06:00');
            if (! $ok) {
                $v->errors()->add('default_time', 'Время должно быть в окне 22:00–06:00.');
            }

            $json = (string) $this->input('exceptions_json', '');
            if ($json !== '') {
                $decoded = json_decode($json, true);
                if (! is_array($decoded)) {
                    $v->errors()->add('exceptions_json', 'Некорректный формат исключений.');
                    return;
                }

                foreach ($decoded as $i => $row) {
                    if (! is_array($row)) {
                        $v->errors()->add('exceptions_json', 'Некорректный формат исключений.');
                        return;
                    }
                    $wd = (string) ($row['window_date'] ?? '');
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $wd)) {
                        $v->errors()->add('exceptions_json', "Некорректная дата в исключениях (#{$i}).");
                        return;
                    }
                    $t = (string) ($row['time_override'] ?? '');
                    if ($t !== '' && ! preg_match('/^\d{2}:\d{2}$/', $t)) {
                        $v->errors()->add('exceptions_json', "Некорректное время в исключениях (#{$i}).");
                        return;
                    }
                    if ($t !== '') {
                        $okT = ($t >= '22:00' && $t <= '23:59') || ($t >= '00:00' && $t <= '06:00');
                        if (! $okT) {
                            $v->errors()->add('exceptions_json', "Время в исключениях должно быть в окне 22:00–06:00 (#{$i}).");
                            return;
                        }
                    }
                }
            }
        });
    }
}
