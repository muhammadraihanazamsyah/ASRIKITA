<?php

use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$schema = 'asrikita_concurrency_'.bin2hex(random_bytes(5));
$barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.$schema.'.go';
DB::statement('CREATE SCHEMA '.$schema);
try {
    DB::statement('SET search_path TO '.$schema);
    Artisan::call('migrate', ['--force' => true]);
    $actor = User::factory()->create(['role' => 'staff', 'status' => 'active']);
    $member = Member::create(['member_code' => 'SIM-CONCURRENT', 'full_name' => 'DATA SIMULASI Konkurensi', 'status' => 'active']);
    $deposit = DB::table('deposits')->insertGetId(['deposit_number' => 'SET-'.Str::ulid(), 'member_id' => $member->id, 'staff_id' => $actor->id, 'occurred_at' => now(), 'status' => 'posted', 'total_amount' => 10000, 'idempotency_key' => (string) Str::uuid()]);
    $credit = new LedgerEntry;
    $credit->forceFill(['member_id' => $member->id, 'deposit_id' => $deposit, 'entry_type' => 'deposit_credit', 'amount_signed' => 10000, 'created_by' => $actor->id])->save();
    $command = [PHP_BINARY, __DIR__.'/withdrawal_worker.php', $schema, (string) $actor->id, (string) $member->id];
    $processes = [];
    foreach ([(string) Str::uuid(), (string) Str::uuid()] as $key) {
        $handle = proc_open([...$command, $key, $barrier], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $processes[] = [$handle, $pipes];
    }
    touch($barrier);
    $outputs = [];
    foreach ($processes as [$handle, $pipes]) {
        $outputs[] = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($handle);
    }
    DB::statement('SET search_path TO '.$schema);
    $balance = (int) DB::table('ledger_entries')->where('member_id', $member->id)->sum('amount_signed');
    $count = DB::table('withdrawals')->count();
    echo json_encode(['workers' => $outputs, 'withdrawals' => $count, 'balance' => $balance], JSON_PRETTY_PRINT).PHP_EOL;
    exit($count === 1 && $balance === 3000 ? 0 : 1);
} finally {
    @unlink($barrier);
    DB::statement('SET search_path TO public');
    DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
}
