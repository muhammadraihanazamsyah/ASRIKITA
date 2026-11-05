<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_https_scheme_is_used_for_vite_assets(): void
    {
        $this->app->make(Vite::class)->useHotFile(storage_path('framework/testing-vite.hot'));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders([
                'Host' => 'asrikita-production.up.railway.app',
                'X-Forwarded-Host' => 'asrikita-production.up.railway.app',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/login');

        $response->assertOk();
        $response->assertSee('https://asrikita-production.up.railway.app/build/assets/', false);
        $response->assertDontSee('http://asrikita-production.up.railway.app/build/assets/', false);
    }
}
