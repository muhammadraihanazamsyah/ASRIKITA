<?php

namespace App\Support;

class DepositInput
{
    public static function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:waste_type_id,weight_kg,expected_price'],
            'items.*.waste_type_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.weight_kg' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/'],
            'items.*.expected_price' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    public static function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.', 'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.', 'gt' => ':attribute harus lebih dari :value.',
            'max.numeric' => ':attribute maksimal :max.', 'min.numeric' => ':attribute minimal :min.',
            'items.max' => 'Maksimal 50 baris dalam satu setoran.', 'items.min' => 'Tambahkan minimal satu jenis sampah.',
            'items.*.weight_kg.regex' => 'Berat harus menggunakan titik desimal, maksimal tiga angka di belakang titik.',
            'items.*.waste_type_id.distinct' => 'Jenis sampah tidak boleh berulang. Gabungkan beratnya dalam satu baris.',
            'idempotency_key.uuid' => 'Identitas formulir tidak valid. Buka formulir setoran baru.',
            'array' => 'Susunan data :attribute tidak valid.',
        ];
    }

    public static function attributes(): array
    {
        return ['member_id' => 'nasabah', 'items' => 'item setoran', 'items.*.weight_kg' => 'berat', 'items.*.waste_type_id' => 'jenis sampah', 'items.*.expected_price' => 'harga yang ditampilkan', 'idempotency_key' => 'identitas formulir'];
    }

    public static function grams(string $weight): int
    {
        [$whole, $fraction] = array_pad(explode('.', $weight, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public static function kilograms(int $grams): string
    {
        return intdiv($grams, 1000).'.'.str_pad((string) ($grams % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function amount(int $grams, int $price): int
    {
        // Validated bounds keep this integer product below PHP_INT_MAX.
        return intdiv($grams * $price + 500, 1000);
    }

    public static function rupiah(int|string $amount): string
    {
        return 'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) $amount);
    }
}
