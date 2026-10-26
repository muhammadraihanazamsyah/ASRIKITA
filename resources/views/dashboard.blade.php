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
        @if($isAdmin)
            <nav aria-label="Master data" class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach(['nasabah' => 'Data nasabah', 'jenis-sampah' => 'Jenis sampah dan harga', 'pengepul' => 'Data pengepul'] as $resource => $label)
                    <a href="{{ route('admin.master.index', $resource) }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50">{{ $label }} →</a>
                @endforeach
            </nav>
        @endif
        <nav aria-label="Transaksi dan saldo" class="mt-6 grid gap-4 sm:grid-cols-3">
            <a href="{{ route('deposits.index') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Pencatatan setoran →</a>
            <a href="{{ route('balances.members') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Tabungan nasabah →</a>
            <a href="{{ route('balances.stock') }}" class="rounded-xl border border-emerald-200 bg-white p-6 font-semibold text-emerald-900">Stok sampah →</a>
        </nav>
        <section class="mt-9 rounded-2xl border border-emerald-100 bg-white p-7" aria-labelledby="status-title">
            <p class="text-xs font-semibold text-emerald-700">PROTOTIPE AKADEMIK · DATA SIMULASI</p>
            <h2 id="status-title" class="mt-3 text-xl font-bold">Ruang kerja sedang disiapkan</h2>
            <p class="mt-3 max-w-2xl leading-relaxed text-slate-600">Login dan akses berdasarkan peran sudah tersedia. Modul operasional berikut belum tersedia dan akan ditambahkan secara bertahap.</p>
            <ul class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                @foreach ($isAdmin ? ['Pengelolaan pengguna', 'Penjualan ke pengepul', 'Pembatalan transaksi', 'Laporan operasional'] : ['Penjualan ke pengepul', 'Pembatalan transaksi', 'Laporan operasional'] as $module)
                    <li class="flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-4 py-3"><span>{{ $module }}</span><span class="shrink-0 text-xs text-slate-500">Belum tersedia</span></li>
                @endforeach
            </ul>
        </section>
        <p class="mt-8 text-xs text-slate-500">Bukan aplikasi resmi Bank Sampah Asri. Seluruh data demo menggunakan data simulasi.</p>
    </main>
</body>
</html>
