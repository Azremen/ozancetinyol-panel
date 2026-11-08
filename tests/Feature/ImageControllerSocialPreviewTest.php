<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageControllerSocialPreviewTest extends TestCase
{
    public function test_social_preview_images_are_cropped_and_served_as_jpeg(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs(
            'settings',
            UploadedFile::fake()->image('social-source.png', 1800, 1200),
            'social-source.png',
        );

        $response = $this->withHeader('Accept', 'image/webp')
            ->get('/img/1200/630?path=settings/social-source.png&format=jpg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Vary', 'Accept');
        $this->assertGreaterThan(0, $response->headers->get('Content-Length'));

        $files = Storage::disk('public')->allFiles('cache/img/1200x630');
        $jpegPath = collect($files)->first(fn (string $path): bool => str_ends_with($path, '/settings/social-source.jpg'));
        $this->assertNotNull($jpegPath);
        $content = Storage::disk('public')->get($jpegPath);
        $image = imagecreatefromstring($content);
        $this->assertNotFalse($image);
        $this->assertSame(1200, imagesx($image));
        $this->assertSame(630, imagesy($image));
        $this->assertLessThan(600 * 1024, strlen($content));
    }

    public function test_image_endpoint_rejects_unsupported_social_preview_format(): void
    {
        $this->get('/img/1200/630?path=settings/social-source.png&format=webp')
            ->assertStatus(422);
    }
}
