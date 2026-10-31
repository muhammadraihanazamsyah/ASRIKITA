@props(['active'])
<nav aria-label="Navigasi tabungan" class="mt-6 flex gap-1 border-b border-[#dbe5df]">
    <a href="{{ route('balances.members') }}" @if($active === 'balances') aria-current="page" @endif class="border-b-2 px-4 py-3 text-sm font-semibold {{ $active === 'balances' ? 'border-[#238b5b] text-[#176b45]' : 'border-transparent text-[#68756e] hover:text-[#176b45]' }}">Tabungan nasabah</a>
    <a href="{{ route('withdrawals.index') }}" @if($active === 'withdrawals') aria-current="page" @endif class="border-b-2 px-4 py-3 text-sm font-semibold {{ $active === 'withdrawals' ? 'border-[#238b5b] text-[#176b45]' : 'border-transparent text-[#68756e] hover:text-[#176b45]' }}">Penarikan</a>
</nav>
