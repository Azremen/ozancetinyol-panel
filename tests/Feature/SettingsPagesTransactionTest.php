<?php

namespace Tests\Feature;

use App\Filament\Pages\HomePageSettings;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SettingsPagesTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_log');
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
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

    public function test_general_settings_save_updates_cached_value(): void
    {
        $this->loginAdmin();
        Setting::create(['key' => 'default_language', 'value' => 'tr', 'type' => 'text']);
        $this->assertSame('tr', Setting::get('default_language'));

        Livewire::test(ManageSettings::class)
            ->set('data.default_language', 'en')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('en', Setting::get('default_language'));
    }

    public function test_homepage_settings_save_updates_cached_translations(): void
    {
        $this->loginAdmin();
        Setting::create([
            'key' => 'home_about_title',
            'value' => ['tr' => 'Önceki', 'en' => 'Before'],
            'type' => 'text',
        ]);
        $this->assertSame('Önceki', Setting::get('home_about_title'));

        Livewire::test(HomePageSettings::class)
            ->set('data.home_about_title.tr', 'Yeni')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Yeni', Setting::get('home_about_title'));
        $this->assertSame('Before', Setting::query()->where('key', 'home_about_title')->firstOrFail()->value['en']);
    }

    private function loginAdmin(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => '01J00000000000000000000000',
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
        ]);
        $user->shouldReceive('hasRole')->andReturn(true);
        $user->shouldReceive('canAccessPanel')->andReturn(true);
        Auth::guard()->setUser($user);
    }
}
