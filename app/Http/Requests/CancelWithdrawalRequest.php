<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelWithdrawalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 5 karakter.',
            'reason.max' => 'Alasan pembatalan maksimal 1000 karakter.',
        ];
    }
}
