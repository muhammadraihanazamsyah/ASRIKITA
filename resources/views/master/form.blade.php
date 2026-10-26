@extends('layouts.admin')
@section('title', ($record->exists ? 'Ubah ' : 'Tambah ').$definition['title'])
@section('content')
    <a href="{{ route('admin.master.index', $resource) }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Kembali ke daftar</a>
    <h1 class="mt-4 text-3xl font-bold tracking-tight">{{ $record->exists ? 'Ubah' : 'Tambah' }} {{ strtolower($definition['title']) }}</h1>
    <p class="mt-3 text-sm text-slate-600">Isian bertanda * wajib diisi. Gunakan identitas simulasi untuk pengembangan dan demonstrasi.</p>
    <form method="POST" action="{{ $record->exists ? route('admin.master.update', [$resource, $record->id]) : route('admin.master.store', $resource) }}" class="mt-7 max-w-3xl space-y-5 rounded-2xl border border-emerald-100 bg-white p-6 sm:p-8" x-data="{ submitting: false }" @submit="if (!confirm('Simpan perubahan data ini?')) { $event.preventDefault(); return; } submitting = true" @pageshow.window="submitting = false">
        @csrf @if($record->exists) @method('PUT') @endif
        @if($resource === 'nasabah')
            <x-master-field name="member_code" label="Kode nasabah" :value="$record->member_code" required maxlength="30" placeholder="SIM-004" />
            <p class="text-xs text-slate-500">Kode harus unik, terdiri dari huruf, angka, dan tanda hubung.</p>
            <x-master-field name="full_name" label="Nama lengkap" :value="$record->full_name" required maxlength="150" />
        @else
            <x-master-field name="name" :label="$resource === 'jenis-sampah' ? 'Nama jenis sampah' : 'Nama pengepul'" :value="$record->name" required :maxlength="$resource === 'jenis-sampah' ? 100 : 150" />
        @endif
        @if($resource === 'jenis-sampah')
            @if($record->exists)<input type="hidden" name="expected_price" value="{{ old('expected_price', $record->current_buy_price) }}">@endif
            <x-master-field name="current_buy_price" label="Harga beli per kilogram (Rp)" type="number" :value="$record->current_buy_price" required min="1" max="1000000000" step="1" inputmode="numeric" />
            <p class="text-xs leading-relaxed text-slate-500">Rupiah bulat tanpa pecahan. Satuan tetap kilogram. Perubahan harga berlaku untuk setoran berikutnya dan tidak mengubah harga pada transaksi lama.</p>
            <x-master-field name="note" label="Catatan perubahan harga (opsional)" maxlength="255" placeholder="Contoh: penyesuaian harga mingguan" />
        @else
            <x-master-field name="phone" label="Telepon (opsional)" type="tel" :value="$record->phone" maxlength="30" />
            <x-master-field name="address" label="Alamat (opsional)" type="textarea" :value="$record->address" maxlength="2000" />
        @endif
        @php($statusName = $definition['status'])
        @php($selectedStatus = (string) old($statusName, $record->exists ? $record->{$statusName} : ($resource === 'nasabah' ? 'active' : '1')))
        <div>
            <label for="{{ $statusName }}" class="block text-sm font-semibold">Status *</label>
            <select id="{{ $statusName }}" name="{{ $statusName }}" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                <option value="{{ $resource === 'nasabah' ? 'active' : '1' }}" @selected(in_array($selectedStatus, ['active', '1'], true))>Aktif</option>
                <option value="{{ $resource === 'nasabah' ? 'inactive' : '0' }}" @selected(in_array($selectedStatus, ['inactive', '0', ''], true))>Nonaktif</option>
            </select>
            @error($statusName)<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap items-center gap-4 border-t border-slate-100 pt-5">
            <button type="submit" :disabled="submitting" :aria-busy="submitting" class="rounded-lg bg-emerald-700 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60" x-text="submitting ? 'Menyimpan…' : 'Simpan data'">Simpan data</button>
            <a href="{{ route('admin.master.index', $resource) }}" class="text-sm text-slate-600 underline">Batal</a>
        </div>
    </form>
    @if($history !== null)
        <section class="mt-9" aria-labelledby="history-title">
            <h2 id="history-title" class="text-xl font-bold">Riwayat harga beli</h2>
            <p class="mt-2 text-sm text-slate-600">Perubahan harga beserta admin yang mencatatnya. Waktu ditampilkan dalam WIB.</p>
            <div class="mt-4 overflow-x-auto rounded-xl border border-emerald-100 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-emerald-50"><tr>@foreach(['Waktu (WIB)', 'Harga lama', 'Harga baru', 'Admin', 'Catatan'] as $heading)<th scope="col" class="whitespace-nowrap px-5 py-4">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($history as $entry)
                            <tr><td class="whitespace-nowrap px-5 py-4">{{ $entry->changed_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td><td class="whitespace-nowrap px-5 py-4">{{ $entry->old_buy_price === null ? 'Harga awal' : 'Rp '.number_format($entry->old_buy_price, 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-4">Rp {{ number_format($entry->new_buy_price, 0, ',', '.') }}</td><td class="px-5 py-4">{{ $entry->changedBy->name }}</td><td class="max-w-xs break-words px-5 py-4">{{ $entry->note ?: '—' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Belum ada perubahan harga tercatat. Harga dari seeder tidak memiliki aktor perubahan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $history->links() }}</div>
        </section>
    @endif
@endsection
