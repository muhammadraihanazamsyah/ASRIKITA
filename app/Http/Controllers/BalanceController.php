<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\WasteType;
use Illuminate\View\View;

class BalanceController extends Controller
{
    public function members(): View
    {
        $members = Member::withSum('ledgerEntries as balance_idr', 'amount_signed')->orderBy('member_code')->paginate(20);

        return view('balances.members', compact('members'));
    }

    public function member(Member $member): View
    {
        $entries = $member->ledgerEntries()->with(['deposit', 'createdBy'])->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $balance = $member->balanceIdr();

        return view('balances.ledger', compact('member', 'entries', 'balance'));
    }

    public function stock(): View
    {
        $types = WasteType::withSum('stockMovements as stock_kg', 'weight_signed')->orderBy('name')->paginate(20);

        return view('balances.stock', compact('types'));
    }

    public function movements(WasteType $wasteType): View
    {
        $movements = $wasteType->stockMovements()->with(['depositItem.deposit', 'createdBy'])->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return view('balances.movements', compact('wasteType', 'movements'));
    }
}
