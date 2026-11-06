<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PanelHtmlMarkupTest extends TestCase
{
    public function test_pagination_disabled_labels_have_valid_roles_on_first_and_last_pages(): void
    {
        foreach (['bootstrap', 'tailwind'] as $theme) {
            foreach ([1, 3] as $page) {
                $paginator = new LengthAwarePaginator(range(1, 10), 30, 10, $page);
                $html = view('vendor.livewire.'.$theme, [
                    'paginator' => $paginator,
                    'elements' => [[1 => '?page=1', 2 => '?page=2', 3 => '?page=3']],
                ])->render();
                $xpath = $this->xpath($html);

                $this->assertSame(0, $xpath->query('//span[@aria-label and not(@role)] | //li[@aria-label]')->length);
                $this->assertGreaterThan(0, $xpath->query('//*[@role="link" and @aria-disabled="true" and @aria-label]')->length);
                $this->assertStringNotContainsString('role="navigation"', $html);
                $this->assertStringContainsString('wire:click=', $html);
            }
        }
    }

    public function test_language_buttons_have_accessible_names_and_selection_state(): void
    {
        foreach (['tr', 'en'] as $locale) {
            app()->setLocale($locale);
            $xpath = $this->xpath(view('livewire.language-switcher')->render());

            $this->assertSame(2, $xpath->query('//button[@aria-label and @type="button"]')->length);
            $this->assertSame(1, $xpath->query('//button[@aria-pressed="true"]')->length);
            $this->assertSame(1, $xpath->query('//button[@aria-pressed="false"]')->length);
        }
    }

    public function test_turkish_language_name_remains_native_in_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('Türkçe', __('panel.common.turkish'));
        $this->assertSame('Türkçe', __('panel.languages.tr'));
        $html = view('livewire.language-switcher')->render();
        $this->assertStringContainsString('aria-label="Türkçe"', $html);
        $this->assertStringNotContainsString('Turkish', $html);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
