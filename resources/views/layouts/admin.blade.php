<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — ASRIKITA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3f7f5] font-sans text-slate-800">
    <header class="bg-[#123c2e] text-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5">
            <a href="{{ route('dashboard') }}" class="text-xl font-bold tracking-wide">ASRIKITA <span class="ml-2 text-xs font-normal text-emerald-100">{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Petugas' }}</span></a>
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="rounded-lg border border-emerald-100/40 px-4 py-2 text-sm hover:bg-white/10">Keluar</button></form>
        </div>
    </header>
    <nav aria-label="Navigasi utama" class="border-b border-emerald-100 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap gap-2 px-5 py-3 text-sm">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-4 py-2 hover:bg-emerald-50">Dashboard</a>
            @if(auth()->user()->role === 'admin')
            @foreach (['nasabah' => 'Nasabah', 'jenis-sampah' => 'Jenis & harga sampah', 'pengepul' => 'Pengepul'] as $key => $label)
                <a href="{{ route('admin.master.index', $key) }}" @if(request()->route('resource') === $key) aria-current="page" @endif class="rounded-lg px-4 py-2 {{ request()->route('resource') === $key ? 'bg-emerald-100 font-semibold text-emerald-950' : 'hover:bg-emerald-50' }}">{{ $label }}</a>
            @endforeach
            @endif
            <a href="{{ route('deposits.index') }}" class="rounded-lg px-4 py-2 hover:bg-emerald-50">Setoran</a>
            <a href="{{ route('sales.index') }}" class="rounded-lg px-4 py-2 hover:bg-emerald-50">Penjualan</a>
            <a href="{{ route('balances.members') }}" class="rounded-lg px-4 py-2 hover:bg-emerald-50">Tabungan</a>
            <a href="{{ route('balances.stock') }}" class="rounded-lg px-4 py-2 hover:bg-emerald-50">Stok</a>
        </div>
    </nav>
    <main class="mx-auto max-w-6xl px-5 py-9">
        <p class="mb-3 text-xs font-semibold tracking-wide text-emerald-700">PROTOTIPE AKADEMIK · DATA SIMULASI</p>
        @if(session('status'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Periksa kembali data berikut:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="mx-auto max-w-6xl px-5 pb-8 text-xs text-slate-500">ASRIKITA · Bank Sampah Asri RW 02 Malaka Sari · Bukan aplikasi resmi</footer>
</body>
</html>
