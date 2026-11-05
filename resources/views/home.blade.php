<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ASRIKITA — Bank Sampah Asri</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-emerald-50 font-sans text-slate-800">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
        <section class="w-full rounded-3xl border border-emerald-100 bg-white p-8 shadow-sm sm:p-12">
            <p class="text-sm font-semibold tracking-widest text-emerald-700">BANK SAMPAH ASRI</p>
            <h1 class="mt-3 text-4xl font-bold text-emerald-950 sm:text-5xl">ASRIKITA</h1>
            <p class="mt-5 text-lg leading-relaxed">Sistem informasi manajemen operasional bank sampah.</p>
            <p class="mt-3 leading-relaxed text-slate-600">Prototipe akademik Capstone Universitas Terbuka untuk studi kasus Bank Sampah Asri, Malaka Sari, Jakarta Timur.</p>
            <div class="mt-8 rounded-xl bg-emerald-50 p-5 text-sm leading-relaxed text-emerald-950">
                Login, master data, transaksi, pembatalan, dashboard statistik, laporan periode, dan ekspor CSV sudah tersedia untuk data simulasi.
            </div>
            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800">Masuk sebagai pengelola</a>
            <div x-data="{ expanded: false }" class="mt-6">
                <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="prototype-note" class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">Tentang prototipe</button>
                <p id="prototype-note" x-show="expanded" x-cloak class="mt-4 text-sm leading-relaxed text-slate-600">ASRIKITA bukan aplikasi resmi Bank Sampah Asri. Data pengembangan dan pengujian menggunakan data simulasi.</p>
            </div>
        </section>
    </main>
</body>
</html>
