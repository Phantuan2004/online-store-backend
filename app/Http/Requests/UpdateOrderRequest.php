<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in(['pending', 'paid', 'shipped', 'completed', 'cancelled']),
            ],
            'address_id' => [
                'sometimes',
                'exists:addresses,id',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái không hợp lệ. Các trạng thái hợp lệ: pending, paid, shipped, completed, cancelled.',
            'address_id.exists' => 'Địa chỉ không tồn tại.',
        ];
    }
}
