@extends('layouts.figma')
@section('title', 'Tabungan nasabah')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-bold">Tabungan & penarikan</h1><p class="mt-3 text-sm text-slate-600">Pantau saldo nasabah dan pencatatan penarikan tabungan.</p></div><a href="{{ route('withdrawals.create') }}" class="rounded-[10px] bg-[#238b5b] px-5 py-3 text-sm font-semibold text-white">＋ Catat penarikan</a></div>
<x-savings-tabs active="balances" />
<p class="mt-5 text-sm text-slate-600">Saldo dihitung dari seluruh mutasi tabungan yang tersimpan.</p>
<div class="mt-6 overflow-x-auto rounded-xl border border-emerald-100 bg-white"><table class="w-full text-left text-sm"><thead class="bg-emerald-50"><tr><th class="px-5 py-4">Nasabah</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Saldo</th><th class="px-5 py-4">Riwayat</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($members as $member)<tr><td class="px-5 py-4">{{ $member->member_code }} — {{ $member->full_name }}</td><td class="px-5 py-4">{{ $member->status === 'active' ? 'Aktif' : 'Nonaktif' }}</td><td class="whitespace-nowrap px-5 py-4 font-semibold">{{ \App\Support\DepositInput::rupiah($member->balance_idr ?? 0) }}</td><td class="px-5 py-4"><a href="{{ route('balances.ledger', $member) }}" class="text-emerald-800 underline">Lihat mutasi</a></td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada nasabah.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $members->links() }}</div>
@endsection
