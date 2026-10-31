@extends('layouts.figma')
@section('title', $definition['title'])
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-bold tracking-tight">{{ $definition['title'] }}</h1><p class="mt-2 text-sm text-slate-600">Kelola data master untuk pencatatan operasional.</p></div>
        <a href="{{ route('admin.master.create', $resource) }}" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800">+ Tambah data</a>
    </div>
    <form method="GET" action="{{ route('admin.master.index', $resource) }}" class="mt-7 flex flex-wrap items-end gap-3 rounded-xl border border-emerald-100 bg-white p-5">
        <div class="min-w-0 flex-1 basis-56"><label for="q" class="block text-sm font-semibold">Cari {{ strtolower($definition['title']) }}</label><input id="q" name="q" value="{{ request('q') }}" maxlength="150" placeholder="{{ $resource === 'nasabah' ? 'Kode, nama, atau telepon' : ($resource === 'pengepul' ? 'Nama atau telepon' : 'Nama jenis sampah') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
        <div><label for="status" class="block text-sm font-semibold">Status</label><select id="status" name="status" class="mt-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Aktif</option><option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option></select></div>
        <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Terapkan</button>
        <a href="{{ route('admin.master.index', $resource) }}" class="px-3 py-2.5 text-sm text-emerald-800 underline">Reset</a>
    </form>
    <section class="mt-6 overflow-hidden rounded-xl border border-emerald-100 bg-white" aria-label="Daftar data">
        @if($records->isEmpty())
            <div class="px-6 py-14 text-center"><h2 class="text-lg font-semibold">Belum ada data yang cocok</h2><p class="mt-2 text-sm text-slate-600">Ubah pencarian atau tambahkan data baru untuk memulai.</p></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-emerald-50 text-emerald-950"><tr>
                        @if($resource === 'nasabah')<th scope="col" class="px-5 py-4">Kode</th>@endif
                        <th scope="col" class="px-5 py-4">Nama</th>
                        <th scope="col" class="px-5 py-4">{{ $resource === 'jenis-sampah' ? 'Harga beli / kg' : 'Telepon' }}</th>
                        <th scope="col" class="px-5 py-4">Status</th><th scope="col" class="px-5 py-4">Tindakan</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($records as $record)
                            @php($active = $resource === 'nasabah' ? $record->status === 'active' : $record->is_active)
                            <tr>
                                @if($resource === 'nasabah')<td class="whitespace-nowrap px-5 py-4 font-medium">{{ $record->member_code }}</td>@endif
                                <td class="min-w-44 max-w-sm break-words px-5 py-4 font-medium">{{ $record->{$definition['name']} }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $resource === 'jenis-sampah' ? 'Rp '.number_format($record->current_buy_price, 0, ',', '.') : ($record->phone ?: '—') }}</td>
                                <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs {{ $active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600' }}">{{ $active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="px-5 py-4"><div class="flex flex-wrap items-center gap-3">
                                    <a href="{{ route('admin.master.edit', [$resource, $record->id]) }}" class="font-semibold text-emerald-800 underline">Ubah</a>
                                    @if($active)
                                        <form method="POST" action="{{ route('admin.master.deactivate', [$resource, $record->id]) }}" x-data="{ submitting: false }" @submit="if (!confirm('Nonaktifkan data ini? Riwayat tetap tersimpan.')) { $event.preventDefault(); return; } submitting = true">
                                            @csrf @method('PATCH')<button :disabled="submitting" class="text-xs text-red-700 underline disabled:opacity-50" x-text="submitting ? 'Memproses…' : 'Nonaktifkan'">Nonaktifkan</button>
                                        </form>
                                    @endif
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    <div class="mt-5">{{ $records->links() }}</div>
    <p class="mt-5 text-xs leading-relaxed text-slate-500">Data dinonaktifkan melalui status agar riwayat tetap tersimpan. Data nonaktif dapat diaktifkan kembali melalui halaman ubah.</p>
@endsection
