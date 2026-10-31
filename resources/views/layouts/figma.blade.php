<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') — ASRIKITA</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f3f6f4] font-sans text-[#17211c] antialiased" x-data="{menu:false}">
@php($navigation=[
 ['route'=>'dashboard','label'=>'Dashboard','icon'=>'dashboard.svg'],
 ['route'=>'admin.master.index','label'=>'Nasabah','icon'=>'users.svg','resource'=>'nasabah','admin'=>true],
 ['route'=>'deposits.index','label'=>'Setoran','icon'=>'deposit.svg'],
 ['route'=>'balances.members','label'=>'Tabungan','icon'=>'wallet.svg'],
 ['route'=>'admin.master.index','label'=>'Jenis & Harga Sampah','icon'=>'tags.svg','resource'=>'jenis-sampah','admin'=>true],
 ['route'=>'balances.stock','label'=>'Inventori','icon'=>'warehouse.svg'],
 ['route'=>'admin.master.index','label'=>'Pengepul','icon'=>'collector.svg','resource'=>'pengepul','admin'=>true],
 ['route'=>'sales.index','label'=>'Penjualan','icon'=>'sales.svg'],
 ['route'=>'reports.index','label'=>'Laporan','icon'=>'report.svg'],
])
<div class="min-h-screen lg:grid lg:grid-cols-[252px_minmax(0,1fr)]">
 <div x-show="menu" x-cloak @click="menu=false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>
 <aside :class="menu?'translate-x-0':'-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-[252px] flex-col bg-[#12372a] px-[18px] py-[25px] text-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
  <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-1"><span class="grid size-[42px] place-items-center rounded-[12px] bg-[#e9f7ef]"><img src="{{ asset('assets/figma/leaf.svg') }}" alt="" class="size-6"></span><span class="text-[19px] font-semibold tracking-[.13em]">ASRIKITA</span></a>
  <p class="mb-2 mt-[27px] px-3 text-[10px] font-bold uppercase tracking-[.08em] text-[#a9c1b5]">Menu utama</p>
  <nav class="space-y-1 text-[13px]">@foreach($navigation as $item) @continue(($item['admin']??false)&&auth()->user()->role!=='admin')
   @php($active=$item['route']==='admin.master.index'?request()->route('resource')===$item['resource']:($item['route']==='dashboard'?request()->routeIs('dashboard','admin.dashboard','staff.dashboard'):request()->routeIs($item['route'])||($item['route']==='balances.members'&&request()->routeIs('withdrawals.*','balances.ledger'))))
   @php($href=$item['route']==='admin.master.index'?route($item['route'],$item['resource']):route($item['route']))
   <a href="{{ $href }}" class="flex h-11 items-center gap-3 rounded-[10px] px-[13px] transition {{ $active?'bg-[#235d45] text-white':'text-[#d8e7df] hover:bg-white/10 hover:text-white' }}"><img src="{{ asset('assets/figma/'.$item['icon']) }}" alt="" class="size-[18px] shrink-0"><span class="flex-1">{{ $item['label'] }}</span>@if($active)<span class="h-[18px] w-1 rounded-full bg-[#69c790]"></span>@endif</a>
  @endforeach</nav>
  <div class="mt-auto rounded-[14px] bg-[#194735] p-4"><p class="text-[10px] font-bold uppercase tracking-[.08em] text-[#85d5a6]">Lingkungan demo</p><p class="mt-2 text-[15px] font-medium">Data simulasi</p><p class="mt-2 text-[10px] leading-relaxed text-[#a9c1b5]">Data digunakan untuk pengembangan dan pengujian internal.</p></div>
  <p class="mt-5 flex justify-between px-1 text-[10px] text-[#a9c1b5]"><span>ASRIKITA</span><span>v1.0</span></p>
 </aside>
 <div class="min-w-0">
  <header class="flex h-[76px] items-center justify-between border-b border-[#e2e8e4] bg-white px-5 lg:px-7">
   <div class="flex min-w-0 items-center gap-3"><button @click="menu=true" class="grid size-9 place-items-center rounded-lg border border-[#e2e8e4] lg:hidden" aria-label="Buka menu">☰</button><span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#e9f7ef] text-[#238b5b]">⌖</span><div class="min-w-0"><p class="truncate text-[13px] font-medium">Bank Sampah Asri RW 02</p><p class="truncate text-[10px] text-[#68756e]">Malaka Sari, Jakarta Timur</p></div></div>
   <div class="flex items-center gap-3"><span class="grid size-[38px] place-items-center rounded-full bg-[#e4f4eb] text-xs font-semibold text-[#176b45]">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><div class="hidden sm:block"><p class="text-xs font-medium">{{ auth()->user()->name }}</p><p class="text-[10px] text-[#68756e]">{{ auth()->user()->role==='admin'?'Administrator':'Petugas' }}</p></div><form action="{{ route('logout') }}" method="POST">@csrf<button class="rounded-lg border border-[#e2e8e4] px-3 py-2 text-xs text-[#445149] hover:bg-[#f3f6f4]">Keluar</button></form></div>
  </header>
  <main class="px-5 py-7 lg:px-7">@if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>@endif @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Periksa kembali data berikut:</strong><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('content')</main>
 </div>
</div>
</body></html>
