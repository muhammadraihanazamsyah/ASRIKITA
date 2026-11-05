<?php

use App\Models\User;
use App\Services\WithdrawalService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $schema, $actorId, $memberId, $key, $barrier] = $argv;
DB::statement('SET search_path TO '.$schema);
while (! file_exists($barrier)) {
    usleep(1000);
}
try {
    app(WithdrawalService::class)->post(['member_id' => (int) $memberId, 'amount' => 7000, 'notes' => 'Uji konkurensi', 'idempotency_key' => $key], User::findOrFail($actorId));
    echo 'SUCCESS';
} catch (Throwable $exception) {
    echo 'REJECTED:'.$exception::class;
}
