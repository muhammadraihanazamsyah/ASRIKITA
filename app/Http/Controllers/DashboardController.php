<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\WasteType;
use App\Support\ReportPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard');
    }

    public function show(Request $request): View
    {
        $period = ReportPeriod::from($request);
        $range = fn ($query) => $query->whereBetween('occurred_at', [$period->startUtc, $period->endUtc]);
        $deposits = Deposit::query()->where('status', 'posted')->tap($range);
        $sales = Sale::query()->where('status', 'posted')->tap($range);
        $stats = [
            'deposit_count' => (clone $deposits)->count(),
            'deposit_amount' => (int) (clone $deposits)->sum('total_amount'),
            'deposit_weight' => (string) DepositItem::query()->whereHas('deposit', fn ($query) => $query->where('status', 'posted')->tap($range))->sum('weight_kg'),
            'sale_count' => (clone $sales)->count(),
            'sale_amount' => (int) (clone $sales)->sum('total_amount'),
            'sale_weight' => (string) SaleItem::query()->whereHas('sale', fn ($query) => $query->where('status', 'posted')->tap($range))->sum('weight_kg'),
            'active_members' => Member::where('status', 'active')->count(),
            'stock_weight' => (string) (StockMovement::query()->selectRaw('COALESCE(SUM(weight_signed), 0)::text AS total')->value('total') ?? '0.000'),
            'total_balance' => (int) LedgerEntry::query()->sum('amount_signed'),
        ];
        $depositTrend = (clone $deposits)->selectRaw("(occurred_at AT TIME ZONE 'Asia/Jakarta')::date::text AS day, SUM(total_amount)::bigint AS total")
            ->groupBy('day')->pluck('total', 'day');
        $saleTrend = (clone $sales)->selectRaw("(occurred_at AT TIME ZONE 'Asia/Jakarta')::date::text AS day, SUM(total_amount)::bigint AS total")
            ->groupBy('day')->pluck('total', 'day');
        $chart = ['labels' => [], 'deposits' => [], 'sales' => []];
        foreach ($period->dates() as $date) {
            $chart['labels'][] = $date;
            $chart['deposits'][] = (int) ($depositTrend[$date] ?? 0);
            $chart['sales'][] = (int) ($saleTrend[$date] ?? 0);
        }
        $recentDeposits = Deposit::with('member')->latest('occurred_at')->limit(5)->get();
        $recentSales = Sale::with('collector')->latest('occurred_at')->limit(5)->get();
        $stockBreakdown = WasteType::query()->where('is_active', true)
            ->withSum('stockMovements as stock_kg', 'weight_signed')
            ->orderByDesc('stock_kg')->limit(5)->get();

        return view('dashboard-figma', [
            'isAdmin' => $request->user()->role === 'admin', 'period' => $period,
            'stats' => $stats, 'chart' => $chart, 'recentDeposits' => $recentDeposits,
            'recentSales' => $recentSales,
            'stockBreakdown' => $stockBreakdown,
        ]);
    }
}
