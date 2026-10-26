@extends('layouts.admin')
@section('title', 'Setoran sampah')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-bold">Setoran sampah</h1><a href="{{ route('deposits.create') }}" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white">+ Catat setoran</a></div>
<p class="mt-3 text-sm text-slate-600">Riwayat setoran yang dicatat oleh admin dan petugas.</p>
<form method="GET" action="{{ route('deposits.index') }}" class="mt-6 flex flex-wrap items-end gap-3"><div class="min-w-0 flex-1"><label for="q" class="block text-sm font-semibold">Cari nomor setoran, kode, atau nama nasabah</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150" class="mt-2 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm"></div><button class="rounded-lg bg-emerald-700 px-5 py-3 text-sm text-white">Cari</button><a href="{{ route('deposits.index') }}" class="px-3 py-3 text-sm text-emerald-800 underline">Reset</a></form>
<div class="mt-6 overflow-x-auto rounded-xl border border-emerald-100 bg-white">
    <table class="w-full text-left text-sm"><thead class="bg-emerald-50"><tr>@foreach(['Nomor / waktu (WIB)', 'Nasabah', 'Pencatat', 'Total', 'Status'] as $heading)<th scope="col" class="px-5 py-4">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
    @forelse($deposits as $deposit)
        <tr><td class="px-5 py-4"><a class="font-semibold text-emerald-800 underline" href="{{ route('deposits.show', $deposit) }}">{{ $deposit->deposit_number }}</a><p class="mt-1 text-xs text-slate-500">{{ $deposit->occurred_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</p></td><td class="px-5 py-4">{{ $deposit->member->member_code }}<br>{{ $deposit->member->full_name }}</td><td class="px-5 py-4">{{ $deposit->staff->name }}</td><td class="whitespace-nowrap px-5 py-4">{{ \App\Support\DepositInput::rupiah($deposit->total_amount) }}</td><td class="px-5 py-4">{{ $deposit->status === 'posted' ? 'Tercatat' : 'Dibatalkan' }}</td></tr>
    @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada setoran yang cocok. Catat setoran baru atau ubah pencarian.</td></tr>@endforelse
    </tbody></table>
</div>
<div class="mt-5">{{ $deposits->links() }}</div>
@endsection
