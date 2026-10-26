<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Akses ditolak — ASRIKITA</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="grid min-h-screen place-items-center bg-emerald-50 px-6 font-sans text-slate-800">
    <main class="max-w-lg rounded-2xl bg-white p-8 shadow-sm">
        <p class="font-semibold text-emerald-700">ASRIKITA · 403</p><h1 class="mt-3 text-3xl font-bold">Akses tidak tersedia</h1>
        <p class="mt-4 text-slate-600">Peran akun Anda tidak memiliki akses ke halaman ini.</p>
        <a href="{{ route('home') }}" class="mt-6 inline-block font-semibold text-emerald-800 underline">Kembali ke beranda</a>
        @auth<form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button type="submit" class="rounded-lg border px-4 py-2">Keluar dari akun</button></form>@endauth
    </main>
</body>
</html>
