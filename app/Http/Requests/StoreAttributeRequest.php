<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:attributes,name'],
            'values' => ['sometimes', 'array'],
            'values.*' => ['required', 'string', 'max:255'],
        ];
    }
}
