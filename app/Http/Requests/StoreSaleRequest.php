<?php

namespace App\Http\Requests;

use App\Support\SaleInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return SaleInput::rules();
    }

    public function messages(): array
    {
        return SaleInput::messages();
    }

    public function attributes(): array
    {
        return SaleInput::attributes();
    }
}
