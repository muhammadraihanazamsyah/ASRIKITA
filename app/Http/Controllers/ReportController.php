<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Withdrawal;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = ReportPeriod::from($request);
        $deposits = $this->depositQuery($period);
        $sales = $this->saleQuery($period);
        $withdrawals = $this->withdrawalQuery($period);
        $summary = [
            'deposit_count' => (clone $deposits)->count(),
            'deposit_amount' => (int) (clone $deposits)->where('status', 'posted')->sum('total_amount'),
            'sale_count' => (clone $sales)->count(),
            'sale_amount' => (int) (clone $sales)->where('status', 'posted')->sum('total_amount'),
            'withdrawal_count' => (clone $withdrawals)->count(),
            'withdrawal_amount' => (int) (clone $withdrawals)->where('status', 'posted')->sum('amount'),
            'movement_count' => $this->movementQuery($period)->count(),
        ];

        return view('reports.index', compact('period', 'summary'));
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, ['deposits', 'sales', 'withdrawals', 'stock-movements'], true), 404);
        $period = ReportPeriod::from($request);
        $filename = "asrikita-{$report}-{$period->startDate}-{$period->endDate}.csv";

        return Response::streamDownload(function () use ($report, $period) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            if ($report === 'deposits') {
                $this->writeDepositCsv($handle, $period);
            } elseif ($report === 'sales') {
                $this->writeSaleCsv($handle, $period);
            } elseif ($report === 'withdrawals') {
                $this->writeWithdrawalCsv($handle, $period);
            } else {
                $this->writeMovementCsv($handle, $period);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function depositQuery(ReportPeriod $period): Builder
    {
        return Deposit::query()->whereBetween('occurred_at', [$period->startUtc, $period->endUtc]);
    }

    private function saleQuery(ReportPeriod $period): Builder
    {
        return Sale::query()->whereBetween('occurred_at', [$period->startUtc, $period->endUtc]);
    }

    private function movementQuery(ReportPeriod $period): Builder
    {
        return StockMovement::query()->whereBetween('created_at', [$period->startUtc, $period->endUtc]);
    }

    private function withdrawalQuery(ReportPeriod $period): Builder
    {
        return Withdrawal::query()->whereBetween('created_at', [$period->startUtc, $period->endUtc]);
    }

    private function writeWithdrawalCsv($handle, ReportPeriod $period): void
    {
        fputcsv($handle, ['Nomor penarikan', 'Waktu WIB', 'Status', 'Kode nasabah', 'Nama nasabah', 'Nominal', 'Catatan', 'Pencatat', 'Waktu pembatalan WIB', 'Alasan pembatalan', 'Pembatal']);
        foreach ($this->withdrawalQuery($period)->with(['member', 'recordedBy', 'cancelledBy'])->orderBy('created_at')->cursor() as $withdrawal) {
            fputcsv($handle, $this->safeRow([
                $withdrawal->withdrawal_number, $withdrawal->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'), $withdrawal->status,
                $withdrawal->member->member_code, $withdrawal->member->full_name, $withdrawal->amount, $withdrawal->notes,
                $withdrawal->recordedBy->name, $withdrawal->cancelled_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                $withdrawal->cancellation_reason, $withdrawal->cancelledBy?->name,
            ]));
        }
    }

    private function writeDepositCsv($handle, ReportPeriod $period): void
    {
        fputcsv($handle, ['Nomor setoran', 'Waktu WIB', 'Status', 'Kode nasabah', 'Nama nasabah', 'Jenis sampah', 'Berat kg', 'Harga beli/kg', 'Nilai item', 'Total transaksi', 'Pencatat']);
        foreach ($this->depositQuery($period)->with(['member', 'staff', 'items.wasteType'])->orderBy('occurred_at')->cursor() as $deposit) {
            foreach ($deposit->items as $item) {
                fputcsv($handle, $this->safeRow([
                    $deposit->deposit_number, $deposit->occurred_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'), $deposit->status,
                    $deposit->member->member_code, $deposit->member->full_name, $item->wasteType->name, $item->weight_kg,
                    $item->buy_price_snapshot, $item->amount, $deposit->total_amount, $deposit->staff->name,
                ]));
            }
        }
    }

    private function writeSaleCsv($handle, ReportPeriod $period): void
    {
        fputcsv($handle, ['Nomor penjualan', 'Waktu WIB', 'Status', 'Pengepul', 'Jenis sampah', 'Berat kg', 'Harga jual/kg', 'Nilai item', 'Total transaksi', 'Pencatat']);
        foreach ($this->saleQuery($period)->with(['collector', 'staff', 'items.wasteType'])->orderBy('occurred_at')->cursor() as $sale) {
            foreach ($sale->items as $item) {
                fputcsv($handle, $this->safeRow([
                    $sale->sale_number, $sale->occurred_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'), $sale->status,
                    $sale->collector->name, $item->wasteType->name, $item->weight_kg, $item->sell_price_snapshot,
                    $item->amount, $sale->total_amount, $sale->staff->name,
                ]));
            }
        }
    }

    private function writeMovementCsv($handle, ReportPeriod $period): void
    {
        fputcsv($handle, ['Waktu WIB', 'Jenis sampah', 'Jenis mutasi', 'Berat bertanda kg', 'Referensi', 'Pencatat']);
        foreach ($this->movementQuery($period)->with(['wasteType', 'createdBy', 'depositItem.deposit', 'saleItem.sale'])->orderBy('created_at')->cursor() as $movement) {
            $reference = $movement->depositItem?->deposit?->deposit_number ?? $movement->saleItem?->sale?->sale_number;
            fputcsv($handle, $this->safeRow([
                $movement->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'), $movement->wasteType->name,
                $movement->movement_type, $movement->weight_signed, $reference, $movement->createdBy->name,
            ]));
        }
    }

    private function safeRow(array $values): array
    {
        return array_map(function ($value) {
            $text = (string) $value;

            $isSignedNumber = preg_match('/^-\d+(?:\.\d+)?$/', $text) === 1;

            return ! $isSignedNumber && preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
        }, $values);
    }
}
