<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class PostgreSqlTestCase extends TestCase
{
    private bool $isolated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('ASRIKITA_DB_TESTS') !== '1') {
            $this->markTestSkipped('Set ASRIKITA_DB_TESTS=1 to run isolated PostgreSQL integration tests.');
        }

        $this->assertSame('pgsql', DB::getDriverName());
        DB::beginTransaction();
        $this->isolated = true;
        $schema = 'asrikita_test_'.bin2hex(random_bytes(8));
        // The schema and all test data disappear with this outer rollback.
        // search_path excludes public, so existing application tables are untouched.
        DB::statement('CREATE SCHEMA '.$schema);
        DB::statement('SET LOCAL search_path TO '.$schema);
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->isolated) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        parent::tearDown();
    }
}
