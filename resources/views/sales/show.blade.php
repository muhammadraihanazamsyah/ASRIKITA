@extends('layouts.admin')
@section('title', 'Detail penjualan')
@section('content')
<a href="{{ route('sales.index') }}" class="text-sm font-semibold text-emerald-800">← Daftar penjualan</a>
<div class="mt-4 flex flex-wrap items-start justify-between gap-5"><div><h1 class="text-3xl font-bold">{{ $sale->sale_number }}</h1><p class="mt-2 text-sm text-slate-600">{{ $sale->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · dicatat {{ $sale->staff->name }}</p></div><span class="rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-900">{{ $sale->status === 'posted' ? 'Tercatat' : 'Dibatalkan' }}</span></div>
<dl class="mt-7 grid gap-4 rounded-xl border border-emerald-100 bg-white p-6 sm:grid-cols-2"><div><dt class="text-xs text-slate-500">Pengepul</dt><dd class="mt-1 font-semibold">{{ $sale->collector->name }}</dd></div><div><dt class="text-xs text-slate-500">Total penjualan</dt><dd class="mt-1 text-xl font-bold">{{ \App\Support\DepositInput::rupiah($sale->total_amount) }}</dd></div></dl>
@if($sale->status === 'void')
<section class="mt-6 rounded-xl border border-red-200 bg-red-50 p-6 text-sm text-red-900"><h2 class="font-bold">Penjualan telah dibatalkan</h2><p class="mt-2">{{ $sale->void_reason }}</p><p class="mt-2 text-xs">Oleh {{ $sale->voidedBy->name }} · {{ $sale->voided_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</p></section>
@endif
<div class="mt-6 overflow-x-auto rounded-xl border border-emerald-100 bg-white"><table class="w-full text-left text-sm"><thead class="bg-emerald-50"><tr>@foreach(['Jenis sampah', 'Berat (kg)', 'Harga jual snapshot', 'Nilai'] as $label)<th class="px-5 py-4">{{ $label }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@foreach($sale->items as $item)<tr><td class="px-5 py-4">{{ $item->wasteType->name }}</td><td class="px-5 py-4">{{ $item->weight_kg }}</td><td class="px-5 py-4">{{ \App\Support\DepositInput::rupiah($item->sell_price_snapshot) }}/kg</td><td class="px-5 py-4 font-semibold">{{ \App\Support\DepositInput::rupiah($item->amount) }}</td></tr>@endforeach</tbody></table></div>
<p class="mt-6 rounded-xl bg-slate-100 p-4 text-sm text-slate-600">Transaksi tidak dapat diedit atau dihapus. Pembatalan mempertahankan transaksi asli dan mengembalikan stok melalui mutasi balik.</p>
@if($sale->status === 'posted')
<form method="POST" action="{{ route('sales.destroy', $sale) }}" class="mt-6 rounded-xl border border-red-200 bg-white p-6" @submit="if (!confirm('Batalkan penjualan ini? Stok akan dikembalikan melalui mutasi balik.')) $event.preventDefault()">
    @csrf @method('PATCH')
    <label for="sale-reason" class="block text-sm font-semibold text-red-900">Alasan pembatalan *</label>
    <textarea id="sale-reason" name="reason" required minlength="5" maxlength="1000" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Contoh: transaksi dengan pengepul dibatalkan">{{ old('reason') }}</textarea>
    <button class="mt-4 rounded-lg border border-red-700 px-5 py-3 text-sm font-semibold text-red-800">Batalkan penjualan</button>
</form>
@endif
@endsection
