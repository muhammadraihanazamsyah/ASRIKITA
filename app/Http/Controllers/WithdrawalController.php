<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelWithdrawalRequest;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Models\Member;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $withdrawals = Withdrawal::query()->with(['member', 'recordedBy'])
            ->when($request->filled('q'), fn ($q) => $q->where(function ($query) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($request->string('q'))).'%';
                $query->where('withdrawal_number', 'ilike', $term)->orWhereHas('member', fn ($member) => $member->where('member_code', 'ilike', $term)->orWhere('full_name', 'ilike', $term));
            }))
            ->when(in_array($request->status, ['posted', 'cancelled'], true), fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();

        return view('withdrawals.index', compact('withdrawals'));
    }

    public function create(): View
    {
        $members = Member::query()->where('status', 'active')->withSum('ledgerEntries as balance_idr', 'amount_signed')->orderBy('member_code')->get();

        return view('withdrawals.create', compact('members'));
    }

    public function store(StoreWithdrawalRequest $request, WithdrawalService $service): RedirectResponse
    {
        $withdrawal = $service->post($request->validated(), $request->user());

        return redirect()->route('withdrawals.show', $withdrawal)->with('status', 'Penarikan tabungan berhasil dicatat.');
    }

    public function show(Withdrawal $withdrawal): View
    {
        $withdrawal->load(['member', 'recordedBy', 'cancelledBy', 'ledgerEntries.createdBy']);

        return view('withdrawals.show', compact('withdrawal'));
    }

    public function destroy(CancelWithdrawalRequest $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $service->cancel($withdrawal, $request->validated('reason'), $request->user());

        return redirect()->route('withdrawals.show', $withdrawal)->with('status', 'Penarikan dibatalkan dan saldo dikembalikan melalui mutasi balik.');
    }
}
