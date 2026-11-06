<?php

namespace Tests\Feature;

use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

class SelectInputAccessibilityTest extends TestCase
{
    public function test_select_input_view_includes_an_id_attribute(): void
    {
        $viewPath = resource_path('views/vendor/filament/support/components/input/select.blade.php');

        $this->assertFileExists($viewPath);

        $html = view()->file($viewPath)
            ->with('attributes', new ComponentAttributeBag([]))
            ->with('slot', '')
            ->render();

        $this->assertMatchesRegularExpression('/<select[^>]*\bid="[^"]+"/', $html);
    }
}
