@extends('layouts.figma')
@section('title', 'Catat penjualan')
@section('content')
<a href="{{ route('sales.index') }}" class="text-sm font-semibold text-emerald-800">← Daftar penjualan</a>
<h1 class="mt-4 text-3xl font-bold">Catat penjualan ke pengepul</h1>
<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">Pilih pengepul, jenis sampah, berat, dan harga jual per kilogram. Harga disimpan sebagai snapshot transaksi; penjualan hanya dapat disimpan jika stok mencukupi.</p>
@if($collectors->isEmpty() || $types->isEmpty())
    <div role="status" class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-6">Belum ada pengepul aktif atau stok sampah yang dapat dijual. Lengkapi master pengepul dan catat setoran terlebih dahulu.</div>
@else
    <noscript><p class="mt-6 rounded-xl bg-amber-50 p-4">Aktifkan JavaScript untuk formulir multi-item dan pemeriksaan stok.</p></noscript>
    <form method="POST" action="{{ route('sales.store') }}" class="mt-7 space-y-6" x-data="saleForm(@js($types), @js(old('items', [])))" @submit="if (!valid || !confirm('Simpan penjualan senilai ' + money(total) + '? Stok akan berkurang.')) { $event.preventDefault(); return; } submitting = true" @pageshow.window="submitting = false">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $key) }}">
        <div class="rounded-xl border border-emerald-100 bg-white p-6">
            <label for="collector_id" class="block text-sm font-semibold">Pengepul *</label>
            <select id="collector_id" name="collector_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
                <option value="">Pilih pengepul aktif</option>
                @foreach($collectors as $collector)<option value="{{ $collector->id }}" @selected((string) old('collector_id') === (string) $collector->id)>{{ $collector->name }}</option>@endforeach
            </select>
            <p class="mt-3 text-xs text-slate-500">Waktu transaksi dicatat otomatis ketika penjualan disimpan.</p>
        </div>
        <div x-cloak class="space-y-4">
            <template x-for="(row, index) in rows" :key="row.key">
                <fieldset class="rounded-xl border border-emerald-100 bg-white p-5">
                    <legend class="px-2 text-sm font-bold text-emerald-900" x-text="'Item ' + (index + 1)"></legend>
                    <div class="grid gap-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto] md:items-end">
                        <div><label :for="'sale-waste-' + row.key" class="block text-sm font-semibold">Jenis sampah *</label><select :id="'sale-waste-' + row.key" :name="'items[' + index + '][waste_type_id]'" x-model="row.waste_type_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">Pilih jenis</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }} — stok {{ $type->stock_kg }} kg</option>@endforeach</select><p class="mt-1 text-xs text-slate-500" x-show="type(row)" x-text="type(row) ? 'Tersedia ' + type(row).stock_kg + ' kg' : ''"></p></div>
                        <div><label :for="'sale-weight-' + row.key" class="block text-sm font-semibold">Berat (kg) *</label><input :id="'sale-weight-' + row.key" :name="'items[' + index + '][weight_kg]'" x-model="row.weight_kg" type="text" inputmode="decimal" required maxlength="11" placeholder="0.000" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></div>
                        <div><label :for="'sale-price-' + row.key" class="block text-sm font-semibold">Harga jual/kg *</label><input :id="'sale-price-' + row.key" :name="'items[' + index + '][sell_price]'" x-model="row.sell_price" type="number" inputmode="numeric" min="1" max="1000000000" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></div>
                        <div><span class="text-xs text-slate-500">Nilai item</span><p class="mt-2 py-3 text-sm font-bold" x-text="money(amount(row))"></p></div>
                        <button type="button" @click="remove(row.key)" :disabled="rows.length === 1" :aria-label="'Hapus item ' + (index + 1)" class="rounded-lg border border-red-200 px-3 py-3 text-sm text-red-700 disabled:opacity-40">Hapus</button>
                    </div>
                </fieldset>
            </template>
            <button type="button" @click="add()" :disabled="rows.length >= 50" class="rounded-lg border border-emerald-700 px-5 py-3 text-sm font-semibold text-emerald-800 disabled:opacity-40">+ Tambah jenis sampah</button>
            <p class="text-xs text-slate-500">Gunakan titik desimal untuk berat, misalnya 1.250. Setiap jenis hanya boleh satu kali dan berat tidak boleh melebihi stok.</p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-5 rounded-xl bg-[#123c2e] p-6 text-white">
            <div><p class="text-sm text-emerald-100">Total nilai penjualan</p><p class="mt-2 text-3xl font-bold" aria-live="polite" data-testid="sale-total" x-text="money(total)">Rp 0</p></div>
            <button type="submit" disabled :disabled="!valid || submitting" :aria-busy="submitting" class="rounded-lg bg-white px-6 py-3 font-semibold text-emerald-900 disabled:cursor-not-allowed disabled:opacity-50" x-text="submitting ? 'Menyimpan…' : 'Simpan penjualan'">Simpan penjualan</button>
        </div>
        <p x-cloak x-show="!valid" class="text-sm text-amber-800">Lengkapi item, gunakan stok yang tersedia, hindari jenis ganda, dan pastikan nilai setiap item minimal Rp 1.</p>
    </form>
@endif
@endsection
