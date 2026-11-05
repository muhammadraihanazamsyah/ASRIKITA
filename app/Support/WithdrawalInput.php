<?php

namespace App\Support;

class WithdrawalInput
{
    public static function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public static function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'min.numeric' => ':attribute minimal Rp 1.',
            'max.numeric' => ':attribute melebihi batas yang didukung sistem.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
            'idempotency_key.uuid' => 'Identitas formulir tidak valid. Buka formulir penarikan baru.',
        ];
    }

    public static function attributes(): array
    {
        return ['member_id' => 'nasabah', 'amount' => 'nominal penarikan', 'notes' => 'catatan', 'idempotency_key' => 'identitas formulir'];
    }
}
