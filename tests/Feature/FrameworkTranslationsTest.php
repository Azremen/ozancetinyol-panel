<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FrameworkTranslationsTest extends TestCase
{
    public function test_turkish_groups_cover_english_messages_and_preserve_placeholders(): void
    {
        $jsonTranslations = json_decode(file_get_contents(lang_path('tr.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['auth', 'passwords', 'pagination', 'validation'] as $group) {
            $english = require lang_path('en/'.$group.'.php');
            $turkish = require lang_path('tr/'.$group.'.php');

            if ($group === 'validation') {
                unset($english['custom'], $english['attributes'], $turkish['custom'], $turkish['attributes']);
            }

            $english = Arr::dot($english);
            $turkish = Arr::dot($turkish);
            $this->assertSame(array_keys($english), array_keys($turkish));

            foreach ($english as $key => $message) {
                $this->assertNotSame('', $turkish[$key]);
                preg_match_all('/:[a-z_]+/', $message, $englishMatches);
                preg_match_all('/:[a-z_]+/', $turkish[$key], $turkishMatches);
                sort($englishMatches[0]);
                sort($turkishMatches[0]);
                $this->assertSame($englishMatches[0], $turkishMatches[0], $group.'.'.$key);
                $fullKey = $group.'.'.$key;
                $this->assertSame($jsonTranslations[$fullKey] ?? $turkish[$key], Lang::get($fullKey, [], 'tr', false));
            }
        }
    }

    public function test_real_validation_errors_are_turkish_and_replace_variables(): void
    {
        app()->setLocale('tr');

        $validator = Validator::make(
            ['email' => 'invalid', 'name' => 'a'],
            ['email' => 'email', 'name' => 'min:3', 'phone' => 'required'],
            [],
            ['email' => 'E-posta', 'name' => 'Ad', 'phone' => 'Telefon'],
        );

        $this->assertSame('E-posta alanı geçerli bir e-posta adresi olmalıdır.', $validator->errors()->first('email'));
        $this->assertSame('Ad alanı en az 3 karakter olmalıdır.', $validator->errors()->first('name'));
        $this->assertSame('Telefon alanı zorunludur.', $validator->errors()->first('phone'));
        $this->assertSame(
            'Çok fazla giriş denemesi yapıldı. Lütfen 60 saniye sonra tekrar deneyin.',
            __('auth.throttle', ['seconds' => 60]),
        );
    }

    public function test_english_messages_remain_unchanged(): void
    {
        app()->setLocale('en');

        $this->assertSame('These credentials do not match our records.', __('auth.failed'));
        $this->assertSame('Your password has been reset.', __('passwords.reset'));
        $this->assertSame('&laquo; Previous', __('pagination.previous'));
    }

    public function test_testimonial_form_labels_are_translated_in_both_languages(): void
    {
        app()->setLocale('tr');
        $this->assertSame('Danışan Adı', __('panel.common.client_name'));
        $this->assertSame('Danışan Unvanı', __('panel.common.client_title'));
        $this->assertSame('Danışan', __('panel.common.guest_title'));
        $this->assertSame('5 Yıldız', '5 '.__('panel.common.stars'));

        app()->setLocale('en');
        $this->assertSame('Client Name', __('panel.common.client_name'));
        $this->assertSame('Client Title', __('panel.common.client_title'));
        $this->assertSame('Client', __('panel.common.guest_title'));
        $this->assertSame('5 Stars', '5 '.__('panel.common.stars'));
    }
}
