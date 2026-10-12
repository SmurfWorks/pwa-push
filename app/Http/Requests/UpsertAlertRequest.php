<?php

namespace App\Http\Requests;

use App\Models\PushApp;
use App\Support\Origins;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'deliver_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:300'],
            'url' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:2000'],
            'tag' => ['nullable', 'string', 'max:120'],
            'grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $app = $this->attributes->get('pushApp');

            if (! $app instanceof PushApp) {
                return;
            }

            foreach (['url', 'icon'] as $field) {
                $value = $this->input($field);

                if (! is_string($value) || $value === '') {
                    continue;
                }

                if (! Origins::matches($value, $app->origins)) {
                    $validator->errors()->add($field, 'Use an address on a site registered for this app.');
                }
            }
        });
    }
}
