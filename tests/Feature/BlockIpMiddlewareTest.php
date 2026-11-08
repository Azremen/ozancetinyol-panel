<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlockIpMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('settings');
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Cache::flush();
    }

    public function test_request_from_blocked_ip_is_rejected(): void
    {
        $this->seedBlockedIp('198.51.100.7');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->get('/robots.txt')
            ->assertForbidden();
    }

    public function test_request_from_allowed_ip_passes(): void
    {
        $this->seedBlockedIp('198.51.100.7');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/robots.txt')
            ->assertOk();
    }

    public function test_request_passes_when_no_ips_are_blocked(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->get('/robots.txt')
            ->assertOk();
    }

    private function seedBlockedIp(string $ip): void
    {
        DB::table('settings')->insert([
            'id' => (string) Str::ulid(),
            'key' => 'blocked_ips',
            'value' => json_encode($ip),
            'type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
