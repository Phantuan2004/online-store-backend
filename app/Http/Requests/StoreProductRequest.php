<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'url'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'unique:product_variants,sku'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.image' => ['nullable', 'string', 'url'],
            'variants.*.attribute_value_ids' => ['nullable', 'array'],
            'variants.*.attribute_value_ids.*' => ['exists:attribute_values,id'],
        ];
    }
}
