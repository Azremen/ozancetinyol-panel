<?php

namespace Tests\Feature;

use App\Helpers\IconHelper;
use Tests\TestCase;

class FacilityIconTest extends TestCase
{
    public function test_supported_icon_previews_preserve_saved_option_values(): void
    {
        app()->setLocale('tr');
        $icons = IconHelper::getFlaticons();

        $this->assertStringContainsString('fa-solid fa-dumbbell', $icons['flaticon-gym']);
        $this->assertStringContainsString('Egzersiz', $icons['flaticon-gym']);
        $this->assertStringContainsString('fa-solid fa-shield-halved', $icons['flaticon-shield']);
        $this->assertStringContainsString('Güvenlik', $icons['flaticon-shield']);
        $this->assertSame(array_keys(IconHelper::ICON_CLASSES), array_keys($icons));
    }
}
