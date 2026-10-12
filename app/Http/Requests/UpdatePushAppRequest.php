<?php

namespace App\Http\Requests;

use App\Rules\AppOrigin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePushAppRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('push_apps', 'name')->ignore($this->route('pushApp'))],
            'origins' => ['sometimes', 'array', 'min:1', 'max:20'],
            'origins.*' => ['required', 'string', 'max:200', new AppOrigin],
        ];
    }
}
