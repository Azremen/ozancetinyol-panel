<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageControllerTest extends TestCase
{
    public function test_zap_image_endpoint_sets_an_httponly_csrf_cookie(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'images/source.png',
            UploadedFile::fake()->image('source.png', 32, 32)->getContent(),
        );

        $response = $this->get('/img/600/760?path=images/source.png')->assertOk();

        $response->assertCookie('XSRF-TOKEN');
        $this->assertTrue($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->deleteDirectory('images');
        Storage::disk('public')->deleteDirectory('cache');

        parent::tearDown();
    }

    public function test_image_resizing_uses_the_public_disk_and_varies_by_accept(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'local']);
        Storage::disk('public')->put(
            'images/source.png',
            UploadedFile::fake()->image('source.png', 32, 32)->getContent(),
        );

        $response = $this->withHeader('Accept', 'image/webp')
            ->get('/img/20/20?path=images/source.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Vary', 'Accept');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_image_dimensions_must_be_positive_and_paths_must_exist(): void
    {
        Storage::fake('public');

        $this->get('/img/0/20?path=images/source.png')->assertNotFound();
        $this->get('/img/20/20?path=images/missing.png')->assertNotFound();
        $this->get('/img/20/20?path=../private/file.png')->assertBadRequest();
    }

    public function test_resized_cache_key_changes_when_source_bytes_change(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('images/source.png', $this->pngWithRedChannel(20));

        $this->get('/img/20/20?path=images/source.png')->assertOk();
        $this->assertCount(1, $disk->allFiles('cache/img/20x20'));

        $disk->put('images/source.png', $this->pngWithRedChannel(220));

        $this->get('/img/20/20?path=images/source.png')->assertOk();
        $this->assertCount(2, $disk->allFiles('cache/img/20x20'));
    }

    private function pngWithRedChannel(int $red): string
    {
        $image = imagecreatetruecolor(32, 32);
        $color = imagecolorallocate($image, $red, 100, 100);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return $content;
    }
}
