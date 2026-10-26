<?php

namespace App\Http\Requests;

use App\Support\DepositInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return DepositInput::rules();
    }

    public function messages(): array
    {
        return DepositInput::messages();
    }

    public function attributes(): array
    {
        return DepositInput::attributes();
    }
}
