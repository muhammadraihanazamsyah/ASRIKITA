<?php

namespace App\Http\Requests;

use App\Support\WithdrawalInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('notes'))) {
            $this->merge(['notes' => trim($this->input('notes')) ?: null]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && in_array($this->user()?->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        return WithdrawalInput::rules();
    }

    public function messages(): array
    {
        return WithdrawalInput::messages();
    }

    public function attributes(): array
    {
        return WithdrawalInput::attributes();
    }
}
