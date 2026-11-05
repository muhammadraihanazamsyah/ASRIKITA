<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->member_code)) {
            $this->merge(['member_code' => strtoupper(trim($this->member_code))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('record');
        $contact = ['phone' => ['nullable', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:2000']];

        return match ($this->route('resource')) {
            'nasabah' => $contact + [
                'member_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', Rule::unique('members', 'member_code')->ignore($id)],
                'full_name' => ['required', 'string', 'max:150'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ],
            'pengepul' => $contact + [
                'name' => ['required', 'string', 'max:150'],
                'is_active' => ['required', 'boolean'],
            ],
            'jenis-sampah' => [
                'name' => ['required', 'string', 'max:100', Rule::unique('waste_types', 'name')->ignore($id)],
                'current_buy_price' => ['required', 'integer', 'min:1', 'max:1000000000'],
                'is_active' => ['required', 'boolean'],
                'note' => ['nullable', 'string', 'max:255'],
                'expected_price' => [$id ? 'required' : 'nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            ],
            default => abort(404),
        };
    }

    public function attributes(): array
    {
        return ['member_code' => 'kode nasabah', 'full_name' => 'nama nasabah', 'name' => 'nama', 'phone' => 'telepon', 'address' => 'alamat', 'current_buy_price' => 'harga beli', 'is_active' => 'status', 'expected_price' => 'harga awal', 'note' => 'catatan harga'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.', 'string' => ':attribute harus berupa teks.',
            'max.string' => ':attribute maksimal :max karakter.', 'max.numeric' => ':attribute maksimal :max.',
            'integer' => ':attribute harus berupa bilangan bulat.', 'min.numeric' => ':attribute minimal :min.',
            'unique' => ':attribute sudah digunakan.', 'in' => ':attribute tidak valid.', 'boolean' => ':attribute tidak valid.',
            'member_code.regex' => 'Kode nasabah hanya boleh berisi huruf, angka, dan tanda hubung.',
        ];
    }
}
