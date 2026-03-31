<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => [
                'required', 
                'string', 
                'max:255',
                \Illuminate\Validation\Rule::unique('attribute_values')->where('attribute_id', $this->route('attribute')->id)
            ],
        ];
    }
}
