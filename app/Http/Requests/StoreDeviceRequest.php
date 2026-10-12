<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
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
            'subscription' => ['required', 'array'],
            'subscription.endpoint' => ['required', 'string', 'max:2000', 'starts_with:https://'],
            'subscription.keys' => ['required', 'array'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:255'],
            'subscription.keys.auth' => ['required', 'string', 'max:255'],
            'subscription.contentEncoding' => ['sometimes', 'nullable', 'in:aes128gcm,aesgcm'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subscription.endpoint.starts_with' => 'The push subscription address must start with https://.',
            'timezone.timezone' => 'Use a timezone name such as America/Los_Angeles.',
        ];
    }
}
