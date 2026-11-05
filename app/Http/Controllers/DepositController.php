<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepositRequest;
use App\Http\Requests\VoidTransactionRequest;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\WasteType;
use App\Services\DepositService;
use App\Services\TransactionReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']]);
        $deposits = Deposit::with(['member', 'staff'])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $query->where(fn ($query) => $query->where('deposit_number', 'ilike', $pattern)->orWhereHas('member', fn ($query) => $query->where('full_name', 'ilike', $pattern)->orWhere('member_code', 'ilike', $pattern)));
            })->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('deposits.index', compact('deposits'));
    }

    public function create(): View
    {
        $members = Member::where('status', 'active')->orderBy('full_name')->get(['id', 'member_code', 'full_name']);
        $types = WasteType::where('is_active', true)->whereBetween('current_buy_price', [1, 1000000000])->orderBy('name')->get(['id', 'name', 'current_buy_price']);
        $key = (string) Str::uuid();

        return view('deposits.create', compact('members', 'types', 'key'));
    }

    public function store(StoreDepositRequest $request, DepositService $service): RedirectResponse
    {
        $deposit = $service->post($request->validated(), $request->user());

        return redirect()->route('deposits.show', $deposit)->with('status', 'Setoran tersimpan. Kredit tabungan dan penambahan stok tercatat satu kali.');
    }

    public function show(Deposit $deposit): View
    {
        $deposit->load(['member', 'staff', 'voidedBy', 'items.wasteType', 'ledgerEntries']);
        $balance = $deposit->member->balanceIdr();

        return view('deposits.show', compact('deposit', 'balance'));
    }

    public function destroy(VoidTransactionRequest $request, Deposit $deposit, TransactionReversalService $service): RedirectResponse
    {
        $service->voidDeposit($deposit, $request->validated('reason'), $request->user());

        return redirect()->route('deposits.show', $deposit)->with('status', 'Setoran dibatalkan. Saldo dan stok telah dicatat melalui mutasi balik.');
    }
}
