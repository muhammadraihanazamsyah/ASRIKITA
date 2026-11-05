<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly CarbonImmutable $startUtc,
        public readonly CarbonImmutable $endUtc,
    ) {}

    public static function from(Request $request): self
    {
        $today = CarbonImmutable::today('Asia/Jakarta');
        $request->mergeIfMissing([
            'start_date' => $today->subDays(29)->toDateString(),
            'end_date' => $today->toDateString(),
        ]);
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'start_date.required' => 'Tanggal awal wajib diisi.',
            'start_date.date_format' => 'Format tanggal awal tidak valid.',
            'end_date.required' => 'Tanggal akhir wajib diisi.',
            'end_date.date_format' => 'Format tanggal akhir tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);
        $start = CarbonImmutable::parse($data['start_date'], 'Asia/Jakarta')->startOfDay();
        $end = CarbonImmutable::parse($data['end_date'], 'Asia/Jakarta')->endOfDay();
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Rentang laporan maksimal 366 hari.']);
        }

        return new self($data['start_date'], $data['end_date'], $start->utc(), $end->utc());
    }

    public function dates(): array
    {
        $dates = [];
        for ($date = CarbonImmutable::parse($this->startDate); $date->lte(CarbonImmutable::parse($this->endDate)); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
