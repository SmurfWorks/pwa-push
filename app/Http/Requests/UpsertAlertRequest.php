<?php

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\PushApp;
use App\Support\DeliveryClock;
use App\Support\Origins;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

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

            $deliverAt = $this->input('deliver_at');

            if (! is_string($deliverAt) || $validator->errors()->has('deliver_at')) {
                return;
            }

            $device = $this->registeredDevice();

            if ($device === null) {
                return;
            }

            try {
                DeliveryClock::resolve($deliverAt, $device->timezone);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('deliver_at', $exception->getMessage());
            }
        });
    }

    private function registeredDevice(): ?Device
    {
        $app = $this->attributes->get('pushApp');
        $id = $this->route('device');

        if (! $app instanceof PushApp || ! is_string($id)) {
            return null;
        }

        return $app->devices()->whereKey($id)->first();
    }
}
