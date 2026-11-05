<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidTransactionRequest;
use App\Models\Collector;
use App\Models\Sale;
use App\Models\WasteType;
use App\Services\SaleService;
use App\Services\TransactionReversalService;
use App\Support\DepositInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']]);
        $sales = Sale::with(['collector', 'staff'])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $query->where(fn ($query) => $query->where('sale_number', 'ilike', $pattern)
                    ->orWhereHas('collector', fn ($query) => $query->where('name', 'ilike', $pattern)));
            })->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        $collectors = Collector::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $types = WasteType::where('is_active', true)->withSum('stockMovements as stock_kg', 'weight_signed')->orderBy('name')->get(['id', 'name'])
            ->filter(fn ($type) => DepositInput::grams((string) ($type->stock_kg ?? '0')) > 0)->values();
        $key = (string) Str::uuid();

        return view('sales.create', compact('collectors', 'types', 'key'));
    }

    public function store(StoreSaleRequest $request, SaleService $service): RedirectResponse
    {
        $sale = $service->post($request->validated(), $request->user());

        return redirect()->route('sales.show', $sale)->with('status', 'Penjualan tersimpan dan pengurangan stok tercatat satu kali.');
    }

    public function show(Sale $sale): View
    {
        $sale->load(['collector', 'staff', 'voidedBy', 'items.wasteType']);

        return view('sales.show', compact('sale'));
    }

    public function destroy(VoidTransactionRequest $request, Sale $sale, TransactionReversalService $service): RedirectResponse
    {
        $service->voidSale($sale, $request->validated('reason'), $request->user());

        return redirect()->route('sales.show', $sale)->with('status', 'Penjualan dibatalkan dan stok telah dikembalikan melalui mutasi balik.');
    }
}
