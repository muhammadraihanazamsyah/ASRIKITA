@extends('layouts.admin')
@section('title', 'Detail setoran')
@section('content')
<a href="{{ route('deposits.index') }}" class="text-sm font-semibold text-emerald-800">← Daftar setoran</a>
<h1 class="mt-4 text-3xl font-bold">Detail setoran</h1><p class="mt-3 break-all font-mono text-sm text-slate-600">{{ $deposit->deposit_number }}</p>
<dl class="mt-6 grid gap-5 rounded-xl border border-emerald-100 bg-white p-6 sm:grid-cols-2">
    <div><dt class="text-xs text-slate-500">Nasabah</dt><dd class="mt-1 font-semibold">{{ $deposit->member->member_code }} — {{ $deposit->member->full_name }}</dd></div>
    <div><dt class="text-xs text-slate-500">Pencatat</dt><dd class="mt-1">{{ $deposit->staff->name }}</dd></div>
    <div><dt class="text-xs text-slate-500">Waktu (WIB)</dt><dd class="mt-1">{{ $deposit->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}</dd></div>
    <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-1">{{ $deposit->status === 'posted' ? 'Tercatat' : 'Dibatalkan' }}</dd></div>
</dl>
<div class="mt-6 overflow-x-auto rounded-xl border border-emerald-100 bg-white"><table class="w-full text-left text-sm"><thead class="bg-emerald-50"><tr>@foreach(['Jenis sampah', 'Berat (kg)', 'Harga saat setoran / kg', 'Nilai'] as $heading)<th scope="col" class="px-5 py-4">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
@foreach($deposit->items as $item)<tr><td class="px-5 py-4">{{ $item->wasteType->name }}</td><td class="px-5 py-4">{{ $item->weight_kg }}</td><td class="whitespace-nowrap px-5 py-4">{{ \App\Support\DepositInput::rupiah($item->buy_price_snapshot) }}</td><td class="whitespace-nowrap px-5 py-4">{{ \App\Support\DepositInput::rupiah($item->amount) }}</td></tr>@endforeach
</tbody><tfoot class="border-t bg-emerald-50"><tr><th colspan="3" class="px-5 py-4">Total setoran</th><td class="whitespace-nowrap px-5 py-4 font-bold">{{ \App\Support\DepositInput::rupiah($deposit->total_amount) }}</td></tr></tfoot></table></div>
<section class="mt-7 rounded-xl border border-emerald-100 bg-white p-6"><h2 class="text-xl font-bold">Mutasi tabungan setoran ini</h2><ul class="mt-4 space-y-2 text-sm">@foreach($deposit->ledgerEntries as $entry)<li>{{ $entry->entry_type === 'deposit_credit' ? 'Kredit setoran' : 'Mutasi balik' }}: {{ \App\Support\DepositInput::rupiah($entry->amount_signed) }}</li>@endforeach</ul><p class="mt-4 font-semibold">Saldo nasabah saat ini: {{ \App\Support\DepositInput::rupiah($balance) }}</p></section>
<p class="mt-5 text-sm text-slate-600">Harga di atas adalah harga historis saat pencatatan. Setoran tersimpan tidak dapat diedit atau dihapus; fitur pembatalan dengan mutasi balik akan ditambahkan pada tahap berikutnya.</p>
<a href="{{ route('deposits.create') }}" class="mt-6 inline-block rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white">Catat setoran baru</a>
@endsection
