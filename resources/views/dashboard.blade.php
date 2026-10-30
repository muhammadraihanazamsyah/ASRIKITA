<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard {{ $isAdmin ? 'Administrator' : 'Petugas' }} — ASRIKITA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-emerald-50/60 font-sans text-slate-800">
    <header class="border-b border-emerald-100 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-5">
            <a href="{{ route('dashboard') }}" class="text-xl font-bold text-emerald-900">ASRIKITA</a>
            <div class="flex items-center gap-4">
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-900">{{ $isAdmin ? 'Administrator' : 'Petugas' }}</span>
                <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Keluar</button></form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-6 py-12">
        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Ruang kerja {{ $isAdmin ? 'administrator' : 'petugas' }}</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">Selamat datang, {{ auth()->user()->name }}.</h1>
        <p class="mt-4 max-w-2xl leading-relaxed text-slate-600">{{ $isAdmin ? 'Akun Anda memiliki peran administrator untuk pengelolaan operasional bank sampah.' : 'Akun Anda memiliki peran petugas untuk pencatatan operasional bank sampah.' }}</p>
        <form method="GET" class="mt-7 flex flex-wrap items-end gap-4 rounded-xl border border-emerald-100 bg-white p-5">
            <div><label for="start_date" class="block text-sm font-semibold">Tanggal awal</label><input id="start_date" name="start_date" type="date" value="{{ $period->startDate }}" required class="mt-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <div><label for="end_date" class="block text-sm font-semibold">Tanggal akhir</label><input id="end_date" name="end_date" type="date" value="{{ $period->endDate }}" required class="mt-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
            <button class="rounded-lg bg-[#123c2e] px-5 py-3 text-sm font-semibold text-white">Terapkan periode</button>
            <a href="{{ route('reports.index', ['start_date' => $period->startDate, 'end_date' => $period->endDate]) }}" class="rounded-lg border border-emerald-700 px-5 py-3 text-sm font-semibold text-emerald-800">Laporan & ekspor →</a>
        </form>
        <section aria-label="Statistik operasional" class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-xl border border-emerald-100 bg-white p-5"><p class="text-xs text-slate-500">Setoran aktif</p><p class="mt-2 text-2xl font-bold">{{ $stats['deposit_count'] }}</p><p class="mt-2 text-sm text-slate-600">{{ \App\Support\DepositInput::rupiah($stats['deposit_amount']) }} · {{ $stats['deposit_weight'] }} kg</p></article>
            <article class="rounded-xl border border-emerald-100 bg-white p-5"><p class="text-xs text-slate-500">Penjualan aktif</p><p class="mt-2 text-2xl font-bold">{{ $stats['sale_count'] }}</p><p class="mt-2 text-sm text-slate-600">{{ \App\Support\DepositInput::rupiah($stats['sale_amount']) }} · {{ $stats['sale_weight'] }} kg</p></article>
            <article class="rounded-xl border border-emerald-100 bg-white p-5"><p class="text-xs text-slate-500">Stok saat ini</p><p class="mt-2 text-2xl font-bold">{{ $stats['stock_weight'] }} kg</p><p class="mt-2 text-sm text-slate-600">Dari seluruh mutasi stok</p></article>
            <article class="rounded-xl border border-emerald-100 bg-white p-5"><p class="text-xs text-slate-500">Nasabah aktif</p><p class="mt-2 text-2xl font-bold">{{ $stats['active_members'] }}</p><p class="mt-2 text-sm text-slate-600">Master data aktif</p></article>
        </section>
        <section class="mt-7 min-w-0 rounded-xl border border-emerald-100 bg-white p-6" aria-labelledby="trend-title"><h2 id="trend-title" class="text-xl font-bold">Tren nilai transaksi</h2><p class="mt-2 text-sm text-slate-600">Nilai rupiah per hari, tidak termasuk transaksi yang dibatalkan.</p><div class="mt-5 h-80 min-w-0 overflow-hidden" x-data="dashboardChart(@js($chart))"><canvas x-ref="canvas" class="max-w-full" aria-label="Grafik nilai setoran dan penjualan" role="img"></canvas></div></section>
        @if($isAdmin)
            <nav aria-label="Master data" class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach(['nasabah' => 'Data nasabah', 'jenis-sampah' => 'Jenis sampah dan harga', 'pengepul' => 'Data pengepul'] as $resource => $label)
                    <a href="{{ route('admin.master.index', $resource) }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50">{{ $label }} →</a>
                @endforeach
            </nav>
        @endif
        <nav aria-label="Transaksi dan saldo" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('deposits.index') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Pencatatan setoran →</a>
            <a href="{{ route('sales.index') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Penjualan ke pengepul →</a>
            <a href="{{ route('balances.members') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Tabungan nasabah →</a>
            <a href="{{ route('balances.stock') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Stok sampah →</a>
        </nav>
        <section class="mt-7 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-emerald-100 bg-white p-6"><div class="flex justify-between gap-4"><h2 class="text-lg font-bold">Setoran terbaru</h2><a href="{{ route('deposits.index') }}" class="text-sm text-emerald-800 underline">Lihat semua</a></div><ul class="mt-4 divide-y divide-slate-100">@forelse($recentDeposits as $deposit)<li class="flex justify-between gap-4 py-3 text-sm"><span><a href="{{ route('deposits.show', $deposit) }}" class="font-semibold text-emerald-800">{{ $deposit->deposit_number }}</a><br><span class="text-slate-500">{{ $deposit->member->full_name }}</span></span><span class="text-right">{{ \App\Support\DepositInput::rupiah($deposit->total_amount) }}<br><span class="text-xs {{ $deposit->status === 'posted' ? 'text-emerald-700' : 'text-red-700' }}">{{ $deposit->status === 'posted' ? 'Tercatat' : 'Dibatalkan' }}</span></span></li>@empty<li class="py-4 text-sm text-slate-500">Belum ada setoran.</li>@endforelse</ul></div>
            <div class="rounded-xl border border-emerald-100 bg-white p-6"><div class="flex justify-between gap-4"><h2 class="text-lg font-bold">Penjualan terbaru</h2><a href="{{ route('sales.index') }}" class="text-sm text-emerald-800 underline">Lihat semua</a></div><ul class="mt-4 divide-y divide-slate-100">@forelse($recentSales as $sale)<li class="flex justify-between gap-4 py-3 text-sm"><span><a href="{{ route('sales.show', $sale) }}" class="font-semibold text-emerald-800">{{ $sale->sale_number }}</a><br><span class="text-slate-500">{{ $sale->collector->name }}</span></span><span class="text-right">{{ \App\Support\DepositInput::rupiah($sale->total_amount) }}<br><span class="text-xs {{ $sale->status === 'posted' ? 'text-emerald-700' : 'text-red-700' }}">{{ $sale->status === 'posted' ? 'Tercatat' : 'Dibatalkan' }}</span></span></li>@empty<li class="py-4 text-sm text-slate-500">Belum ada penjualan.</li>@endforelse</ul></div>
        </section>
        @if($isAdmin)<p class="mt-7 rounded-xl bg-slate-100 p-4 text-sm text-slate-600">Pengelolaan pengguna melalui antarmuka belum tersedia; akun operator dibuat melalui command Artisan.</p>@endif
        <p class="mt-8 text-xs text-slate-500">Bukan aplikasi resmi Bank Sampah Asri. Seluruh data demo menggunakan data simulasi.</p>
    </main>
</body>
</html>
