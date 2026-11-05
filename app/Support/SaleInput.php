<?php

namespace App\Support;

class SaleInput
{
    public static function rules(): array
    {
        return [
            'collector_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:waste_type_id,weight_kg,sell_price'],
            'items.*.waste_type_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.weight_kg' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/'],
            'items.*.sell_price' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    public static function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.', 'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.', 'gt' => ':attribute harus lebih dari :value.',
            'max.numeric' => ':attribute maksimal :max.', 'min.numeric' => ':attribute minimal :min.',
            'items.max' => 'Maksimal 50 baris dalam satu penjualan.', 'items.min' => 'Tambahkan minimal satu jenis sampah.',
            'items.*.weight_kg.regex' => 'Berat harus menggunakan titik desimal, maksimal tiga angka di belakang titik.',
            'items.*.waste_type_id.distinct' => 'Jenis sampah tidak boleh berulang. Gabungkan beratnya dalam satu baris.',
            'idempotency_key.uuid' => 'Identitas formulir tidak valid. Buka formulir penjualan baru.',
            'array' => 'Susunan data :attribute tidak valid.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'collector_id' => 'pengepul', 'items' => 'item penjualan',
            'items.*.weight_kg' => 'berat', 'items.*.waste_type_id' => 'jenis sampah',
            'items.*.sell_price' => 'harga jual', 'idempotency_key' => 'identitas formulir',
        ];
    }
}
