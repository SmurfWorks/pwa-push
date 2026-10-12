<?php

namespace App\Http\Requests;

use App\Rules\AppOrigin;
use Illuminate\Foundation\Http\FormRequest;

class StorePushAppRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80', 'unique:push_apps,name'],
            'origins' => ['required', 'array', 'min:1', 'max:20'],
            'origins.*' => ['required', 'string', 'max:200', new AppOrigin],
        ];
    }
}
