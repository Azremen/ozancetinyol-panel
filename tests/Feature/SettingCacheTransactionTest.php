<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingCacheTransactionTest extends TestCase
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
        Schema::dropIfExists('activity_log');
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
        Cache::flush();
    }

    public function test_setting_cache_is_invalidated_after_a_successful_transaction(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Before', 'type' => 'text']);
        $this->assertSame('Before', Setting::get('site_name'));

        DB::beginTransaction();
        Setting::where('key', 'site_name')->firstOrFail()->update(['value' => 'After']);
        $this->assertSame('Before', Setting::get('site_name'));
        DB::commit();

        $this->assertSame('After', Setting::get('site_name'));
    }
}
