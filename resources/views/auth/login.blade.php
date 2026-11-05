<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — ASRIKITA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3f7f5] font-sans text-[#192a22]">
<div class="min-h-screen lg:grid lg:grid-cols-[minmax(320px,0.8fr)_1.5fr]">
    <aside class="relative overflow-hidden bg-[#123c2e] px-7 py-8 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div aria-hidden="true" class="pointer-events-none absolute -right-28 -top-32 size-80 rounded-full border-40 border-emerald-200/10"></div>
        <a href="{{ route('home') }}" class="relative inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
            <span class="grid size-12 place-items-center rounded-2xl bg-white text-2xl text-emerald-800" aria-hidden="true">♧</span>
            <span><span class="block text-lg font-bold">Bank Sampah Asri</span><span class="text-sm text-emerald-100/75">RW 02 · Malaka Sari</span></span>
        </a>
        <div class="relative mt-10 max-w-md lg:my-16">
            <p class="inline-block rounded-full bg-emerald-200/15 px-4 py-2 text-xs font-semibold text-emerald-100">Bersama merawat lingkungan</p>
            <h1 class="mt-6 text-3xl font-bold leading-tight lg:text-4xl">Pencatatan yang rapi,<br>langkah kecil yang berarti.</h1>
            <p class="mt-5 hidden text-base leading-relaxed text-emerald-100/75 lg:block">ASRIKITA membantu pengelola menata operasional bank sampah dengan pencatatan yang terhubung.</p>
        </div>
        <p class="mt-6 max-w-sm text-xs leading-relaxed text-emerald-100/70">Prototipe akademik Universitas Terbuka.<br>Bukan aplikasi resmi Bank Sampah Asri.</p>
    </aside>
    <div class="flex min-w-0 flex-col">
        <header class="border-b border-emerald-950/10 bg-white px-6 py-6 lg:px-10">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Kembali ke beranda</a>
        </header>
        <main class="flex flex-1 items-center justify-center px-5 py-10 lg:py-16">
            <section class="w-full max-w-lg rounded-2xl border border-emerald-950/10 bg-white p-6 shadow-sm sm:p-9" aria-labelledby="login-title">
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-800">Akses pengelola</p>
                <h2 id="login-title" class="mt-3 text-3xl font-bold tracking-tight">Selamat datang kembali</h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">Masuk menggunakan akun administrator atau petugas yang telah diaktifkan.</p>
                @if (session('status'))
                    <p role="status" class="mt-5 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <div role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5" x-data="{ visible: false, submitting: false }" @submit="submitting = true" @pageshow.window="submitting = false">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-semibold">Email <span aria-hidden="true">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username" autofocus aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700" placeholder="nama@example.com">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-semibold">Kata sandi <span aria-hidden="true">*</span></label>
                        <div class="relative mt-2">
                            <input id="password" name="password" type="password" :type="visible ? 'text' : 'password'" required maxlength="1024" autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="w-full rounded-lg border border-slate-300 py-3 pl-4 pr-24 text-sm focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700" placeholder="Masukkan kata sandi">
                            <button type="button" x-cloak @click="visible = !visible" :aria-pressed="visible" aria-controls="password" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-emerald-800" x-text="visible ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-emerald-700"> Ingat saya di perangkat ini</label>
                    <button type="submit" :disabled="submitting" :aria-busy="submitting" class="w-full rounded-lg bg-[#218b5e] px-4 py-3.5 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700 disabled:cursor-wait disabled:opacity-70" x-text="submitting ? 'Sedang masuk…' : 'Masuk'">Masuk</button>
                </form>
                <p class="mt-6 rounded-lg bg-emerald-50 p-4 text-xs leading-relaxed text-slate-600">Kesulitan masuk? Hubungi administrator proyek untuk memeriksa akses akun Anda. Portal dan registrasi nasabah belum tersedia.</p>
            </section>
        </main>
        <footer class="px-6 pb-6 text-center text-xs text-slate-500">ASRIKITA · Prototipe akademik · Data pengembangan adalah simulasi</footer>
    </div>
</div>
</body>
</html>
