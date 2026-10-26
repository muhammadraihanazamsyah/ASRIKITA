@extends('layouts.admin')
@section('title', 'Stok sampah')
@section('content')
<h1 class="text-3xl font-bold">Stok sampah</h1><p class="mt-3 text-sm text-slate-600">Stok berasal dari jumlah mutasi inventori; satuan kilogram.</p>
<div class="mt-6 overflow-x-auto rounded-xl border border-emerald-100 bg-white"><table class="w-full text-left text-sm"><thead class="bg-emerald-50"><tr><th class="px-5 py-4">Jenis sampah</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Stok (kg)</th><th class="px-5 py-4">Riwayat</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($types as $type)<tr><td class="px-5 py-4">{{ $type->name }}</td><td class="px-5 py-4">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</td><td class="px-5 py-4 font-semibold">{{ $type->stock_kg ?? '0.000' }}</td><td class="px-5 py-4"><a href="{{ route('balances.movements', $type) }}" class="text-emerald-800 underline">Lihat mutasi</a></td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada jenis sampah.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $types->links() }}</div>
@endsection
