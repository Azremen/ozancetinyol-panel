<?php

namespace Tests\Feature;

use App\Filament\Resources\Sliders\Tables\SlidersTable;
use App\Http\Middleware\TrustProxies;
use App\Models\Page;
use App\Models\Setting;
use App\Support\UploadRules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
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

    public function test_livewire_temporary_uploads_use_a_private_disk_and_image_only_rules(): void
    {
        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
        $this->assertNotSame('public', config('filesystems.disks.local.visibility', 'private'));

        $rules = config('livewire.temporary_file_upload.rules');

        $this->assertContains('required', $rules);
        $this->assertContains('file', $rules);
        $this->assertContains('mimes:'.implode(',', UploadRules::TEMPORARY_UPLOAD_EXTENSIONS), $rules);

        foreach (['php', 'phtml', 'svg', 'html', 'js'] as $dangerous) {
            $this->assertNotContains($dangerous, UploadRules::TEMPORARY_UPLOAD_EXTENSIONS);
        }
    }

    public function test_image_upload_allow_lists_exclude_svg(): void
    {
        $this->assertNotContains('image/svg+xml', UploadRules::IMAGE_MIME_TYPES);
        $this->assertNotContains('image/svg+xml', UploadRules::FAVICON_MIME_TYPES);
        $this->assertNotContains('image/*', UploadRules::IMAGE_MIME_TYPES);
    }

    public function test_no_filament_upload_uses_the_permissive_image_helper(): void
    {
        $files = collect(glob(app_path('Filament/**/*.php')))
            ->merge(glob(app_path('Filament/**/**/*.php')))
            ->merge(glob(app_path('Filament/**/**/**/*.php')))
            ->unique();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertStringNotContainsString(
                '->image()',
                (string) file_get_contents($file),
                basename($file).' must use UploadRules::IMAGE_MIME_TYPES instead of ->image() (accepts SVG).'
            );
        }
    }

    public function test_slider_preview_urls_are_escaped_and_path_restricted(): void
    {
        $this->assertNull(SlidersTable::resolvePreviewUrl('x" onerror="alert(1)'));
        $this->assertNull(SlidersTable::resolvePreviewUrl('../../.env'));
        $this->assertNull(SlidersTable::resolvePreviewUrl('javascript:alert(1)'));
        $this->assertNull(SlidersTable::resolvePreviewUrl(''));
        $this->assertNull(SlidersTable::resolvePreviewUrl(['not', 'a', 'string']));

        $url = SlidersTable::resolvePreviewUrl('sliders/hero-1.jpg');
        $this->assertIsString($url);
        $this->assertStringEndsWith('/storage/sliders/hero-1.jpg', $url);

        $this->assertSame('https://cdn.example.com/a.jpg', SlidersTable::resolvePreviewUrl('https://cdn.example.com/a.jpg'));
    }

    public function test_secret_settings_are_masked_in_the_activity_log(): void
    {
        $setting = Setting::create(['key' => 'recaptcha_secret_key', 'value' => 'super-secret', 'type' => 'text']);
        $setting->update(['value' => 'rotated-secret']);

        $public = Setting::create(['key' => 'site_name', 'value' => 'Clinic', 'type' => 'text']);

        $secretActivities = Activity::query()
            ->where('subject_type', Setting::class)
            ->where('subject_id', $setting->getKey())
            ->get();

        $this->assertNotEmpty($secretActivities);

        foreach ($secretActivities as $activity) {
            $json = json_encode($activity->properties);
            $this->assertStringNotContainsString('super-secret', $json);
            $this->assertStringNotContainsString('rotated-secret', $json);
            $this->assertStringContainsString(Setting::MASKED_VALUE, $json);
        }

        $publicActivity = Activity::query()
            ->where('subject_type', Setting::class)
            ->where('subject_id', $public->getKey())
            ->firstOrFail();

        $this->assertStringContainsString('Clinic', json_encode($publicActivity->properties));
    }

    public function test_map_embed_urls_are_restricted_to_exact_google_hosts(): void
    {
        $this->assertSame(
            'https://www.google.com/maps/embed?pb=abc',
            Page::normalizeMapEmbedUrl('<iframe src="https://google.com/maps/embed?pb=abc"></iframe>')
        );
        $this->assertSame(
            'https://maps.google.com/maps?q=istanbul&output=embed',
            Page::normalizeMapEmbedUrl('https://maps.google.com/maps?q=istanbul&output=embed')
        );

        foreach ([
            'https://google.attacker.com/maps/embed?pb=abc',
            'https://maps.google.attacker.com/maps/embed?pb=abc',
            'https://www.google.com.evil.example/maps/embed?pb=abc',
            'https://lh3.googleusercontent.com/maps/embed?pb=abc',
            'https://www.google.com/evil/maps/embed?pb=abc',
            'https://www.google.com@attacker.com/maps/embed?pb=abc',
            'https://www.google.com:8443/maps/embed?pb=abc',
            'ftp://www.google.com/maps/embed?pb=abc',
        ] as $rejected) {
            $this->assertNull(Page::normalizeMapEmbedUrl($rejected), $rejected.' must be rejected');
        }
    }

    public function test_trusted_proxies_middleware_reads_configuration(): void
    {
        $middleware = new TrustProxies;
        $method = new \ReflectionMethod($middleware, 'proxies');

        config(['app.trusted_proxies' => null]);
        $this->assertNull($method->invoke($middleware));

        config(['app.trusted_proxies' => ' 10.0.0.1, 192.168.0.0/16 ']);
        $this->assertSame(['10.0.0.1', '192.168.0.0/16'], $method->invoke($middleware));

        config(['app.trusted_proxies' => '*']);
        $this->assertSame('*', $method->invoke($middleware));
    }

    public function test_htaccess_blocks_script_execution_under_storage(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $this->assertMatchesRegularExpression('/RewriteRule \^storage\/.*php.*\[F/', $htaccess);
        $this->assertStringContainsString('sandbox', $htaccess);
    }
}
