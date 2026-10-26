<?php

namespace Database\Seeders;

use App\Models\Collector;
use App\Models\Member;
use App\Models\WasteType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimulationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data simulasi hanya boleh di-seed pada local/testing.');
        }

        DB::transaction(function (): void {
            // firstOrCreate makes reruns harmless and never overwrites edited records.
            foreach (['001', '002', '003'] as $code) {
                Member::firstOrCreate(['member_code' => 'SIM-'.$code], [
                    'full_name' => 'DATA SIMULASI — Nasabah '.$code,
                    'status' => 'active',
                ]);
            }

            foreach (['Plastik PET' => 3000, 'Kardus' => 2000, 'Aluminium' => 10000] as $name => $price) {
                WasteType::firstOrCreate(['name' => 'DATA SIMULASI — '.$name], [
                    'unit' => 'kg', 'current_buy_price' => $price, 'is_active' => true,
                ]);
            }

            Collector::firstOrCreate(['name' => 'DATA SIMULASI — Pengepul 001'], ['is_active' => true]);
        });

        $this->command?->info('DATA SIMULASI: master nasabah, sampah, dan pengepul disiapkan tanpa akun login atau transaksi.');
    }
}
