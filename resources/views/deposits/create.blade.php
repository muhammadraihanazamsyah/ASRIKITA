@extends('layouts.admin')
@section('title', 'Catat setoran')
@section('content')
<a href="{{ route('deposits.index') }}" class="text-sm font-semibold text-emerald-800">← Daftar setoran</a>
<h1 class="mt-4 text-3xl font-bold">Catat setoran sampah</h1>
<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">Pilih nasabah dan jenis sampah, lalu masukkan hasil penimbangan. Berat dalam kilogram dengan maksimal tiga desimal; nilai rupiah dibulatkan pada setiap baris.</p>
@if($members->isEmpty() || $types->isEmpty())
    <div role="status" class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-6">Belum ada nasabah aktif atau jenis sampah dengan harga valid. Hubungi admin untuk melengkapi master data sebelum mencatat setoran.</div>
@else
    <noscript><p class="mt-6 rounded-xl bg-amber-50 p-4">Aktifkan JavaScript untuk formulir multi-item dan pratinjau nilai setoran.</p></noscript>
    <form method="POST" action="{{ route('deposits.store') }}" class="mt-7 space-y-6" x-data="depositForm(@js($types), @js(old('items', [])))" @submit="if (!valid || !confirm('Simpan setoran senilai ' + money(total) + '? Saldo dan stok akan bertambah.')) { $event.preventDefault(); return; } submitting = true" @pageshow.window="submitting = false">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $key) }}">
        <div class="rounded-xl border border-emerald-100 bg-white p-6">
            <label for="member_id" class="block text-sm font-semibold">Nasabah *</label>
            <select id="member_id" name="member_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
                <option value="">Pilih nasabah aktif</option>
                @foreach($members as $member)<option value="{{ $member->id }}" @selected((string) old('member_id') === (string) $member->id)>{{ $member->member_code }} — {{ $member->full_name }}</option>@endforeach
            </select>
            <p class="mt-3 text-xs text-slate-500">Waktu transaksi dicatat otomatis ketika setoran disimpan.</p>
        </div>
        <div x-cloak class="space-y-4">
            <template x-for="(row, index) in rows" :key="row.key">
                <fieldset class="rounded-xl border border-emerald-100 bg-white p-5">
                    <legend class="px-2 text-sm font-bold text-emerald-900" x-text="'Item ' + (index + 1)"></legend>
                    <div class="grid gap-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto] md:items-end">
                        <div><label :for="'waste-' + row.key" class="block text-sm font-semibold">Jenis sampah *</label><select :id="'waste-' + row.key" :name="'items[' + index + '][waste_type_id]'" x-model="row.waste_type_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">Pilih jenis</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
                        <div><label :for="'weight-' + row.key" class="block text-sm font-semibold">Berat (kg) *</label><input :id="'weight-' + row.key" :name="'items[' + index + '][weight_kg]'" x-model="row.weight_kg" type="text" inputmode="decimal" required maxlength="11" placeholder="0.000" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></div>
                        <div><span class="text-xs text-slate-500">Harga / kg</span><p class="mt-2 py-3 text-sm font-medium" x-text="money(type(row)?.current_buy_price ?? null)"></p><input type="hidden" :name="'items[' + index + '][expected_price]'" :value="type(row)?.current_buy_price ?? ''"></div>
                        <div><span class="text-xs text-slate-500">Nilai item</span><p class="mt-2 py-3 text-sm font-bold" x-text="money(amount(row))"></p></div>
                        <button type="button" @click="remove(row.key)" :disabled="rows.length === 1" :aria-label="'Hapus item ' + (index + 1)" class="rounded-lg border border-red-200 px-3 py-3 text-sm text-red-700 disabled:opacity-40">Hapus</button>
                    </div>
                </fieldset>
            </template>
            <button type="button" @click="add()" :disabled="rows.length >= 50" class="rounded-lg border border-emerald-700 px-5 py-3 text-sm font-semibold text-emerald-800 disabled:opacity-40">+ Tambah jenis sampah</button>
            <p class="text-xs text-slate-500">Gunakan titik desimal, misalnya 1.250. Setiap jenis hanya boleh satu kali. Maksimal 50 item dan 1.000.000 kg per item.</p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-5 rounded-xl bg-[#123c2e] p-6 text-white">
            <div><p class="text-sm text-emerald-100">Total kredit tabungan</p><p class="mt-2 text-3xl font-bold" aria-live="polite" data-testid="deposit-total" x-text="money(total)">Rp 0</p></div>
            <button type="submit" disabled :disabled="!valid || submitting" :aria-busy="submitting" class="rounded-lg bg-white px-6 py-3 font-semibold text-emerald-900 disabled:cursor-not-allowed disabled:opacity-50" x-text="submitting ? 'Menyimpan…' : 'Simpan setoran'">Simpan setoran</button>
        </div>
        <p x-cloak x-show="!valid" class="text-sm text-amber-800">Lengkapi jenis dan berat yang valid, hindari jenis ganda, serta pastikan nilai setiap item minimal Rp 1.</p>
    </form>
@endif
@endsection
