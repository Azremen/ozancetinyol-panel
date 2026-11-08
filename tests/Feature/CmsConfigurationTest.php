<?php

namespace Tests\Feature;

use App\Filament\Pages\HomePageSettings;
use App\Filament\Pages\LegalPageSettings;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Url;
use App\Models\User;
use App\Services\PageUrls;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CmsConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::disableForeignKeyConstraints();
        foreach (['urls', 'menus', 'pages', 'media', 'settings', 'activity_log', 'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach ([
            '2026_03_02_165406_create_permission_tables.php',
            '2026_03_02_170002_create_menus_table.php',
            '2026_03_02_170003_create_pages_table.php',
            '2026_03_02_180001_create_urls_table.php',
            '2026_03_02_190150_create_media_table.php',
            '2026_03_02_223509_create_settings_table.php',
            '2026_03_25_120000_add_order_to_menus_table.php',
            '2026_03_25_130000_add_meta_to_menus_table.php',
            '2026_04_19_120000_add_map_embed_url_to_pages_table.php',
        ] as $migration) {
            (require database_path('migrations\\'.$migration))->up();
        }
        require_once database_path('migrations\\2026_03_12_115430_create_activity_log_table.php');
        (new \CreateActivityLogTable)->up();
        Schema::enableForeignKeyConstraints();
        $this->login(true);
    }

    public function test_menu_create_enforces_header_depth_footer_parent_and_removed_sidebar(): void
    {
        $root = $this->menu();
        $child = $this->menu(['parent_id' => $root->id]);
        foreach ([
            ['type' => 'header', 'parent_id' => $child->id],
            ['type' => 'footer', 'parent_id' => $root->id],
            ['type' => 'sidebar'],
        ] as $invalid) {
            Livewire::test(CreateMenu::class)->fillForm($invalid + ['title' => ['tr' => 'Yeni']])
                ->call('create')->assertHasFormErrors(['type']);
        }
        $this->assertSame(2, Menu::count());
        Livewire::test(CreateMenu::class)->fillForm(['type' => 'header', 'parent_id' => $root->id, 'title' => ['tr' => 'Alt']])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateMenu::class)->fillForm(['type' => 'footer', 'title' => ['tr' => 'Alt bilgi']])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(4, Menu::count());
    }

    public function test_menu_edit_prevents_self_cycles_reparenting_children_and_footer_nesting(): void
    {
        $root = $this->menu();
        $child = $this->menu(['parent_id' => $root->id]);
        $other = $this->menu();
        foreach ([
            ['parent_id' => $root->id],
            ['parent_id' => $child->id],
            ['parent_id' => $other->id],
            ['type' => 'footer'],
        ] as $invalid) {
            Livewire::test(EditMenu::class, ['record' => $root->id])->fillForm($invalid)
                ->call('save')->assertHasFormErrors(['type']);
        }
        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame('header', $root->fresh()->type);
        Livewire::test(EditMenu::class, ['record' => $child->id])->fillForm(['parent_id' => null, 'type' => 'footer'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('footer', $child->fresh()->type);
    }

    public function test_menu_title_can_be_updated_from_the_edit_form(): void
    {
        $page = Page::create(['title' => ['tr' => 'Sorular', 'en' => 'Questions']]);
        $menu = $this->menu([
            'title' => ['tr' => 'Eski başlık', 'en' => 'FAQs'],
            'page_id' => $page->id,
        ]);

        Livewire::test(EditMenu::class, ['record' => $menu->id])
            ->set('data.title.tr', 'Sıkça sorulan sorular')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Sıkça sorulan sorular', $menu->fresh()->title['tr']);
        $this->assertSame('FAQs', $menu->fresh()->title['en']);
        $this->assertSame($page->id, $menu->fresh()->page_id);
    }

    public function test_legacy_sidebar_and_nested_records_are_preserved_and_can_be_repaired(): void
    {
        $root = $this->menu();
        $child = $this->menu(['parent_id' => $root->id]);
        $deep = $this->menu();
        DB::table('menus')->where('id', $deep->id)->update(['parent_id' => $child->id]);
        Livewire::test(EditMenu::class, ['record' => $deep->id])->fillForm(['parent_id' => null])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($deep->fresh()->parent_id);
        DB::table('menus')->where('id', $root->id)->update(['type' => 'sidebar', 'meta_title' => 'Preserved']);
        Livewire::test(EditMenu::class, ['record' => $root->id])->fillForm(['title.tr' => 'Eski'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('sidebar', $root->fresh()->type);
        $this->assertSame('Preserved', $root->fresh()->meta_title);
        DB::table('menus')->where('id', $child->id)->update(['type' => 'footer']);
        Livewire::test(EditMenu::class, ['record' => $child->id])->fillForm(['parent_id' => null])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(3, Menu::count());
    }

    public function test_page_form_creates_normalized_url_and_edit_preserves_aliases_and_title_changes(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['title' => ['tr' => 'Özel Tedavi', 'en' => 'Treatment'], 'slug' => ' /Özel Tedavi/ '])
            ->call('create')->assertHasNoFormErrors();
        $page = Page::firstOrFail();
        $canonical = PageUrls::canonical($page);
        $this->assertSame('ozel-tedavi', $canonical->slug);
        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title.tr' => 'Başlık değişti'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('ozel-tedavi', PageUrls::canonical($page->fresh())->slug);
        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['slug' => '/tedavi/yeni/'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($canonical->id, PageUrls::canonical($page)->id);
        $this->assertSame('tedavi/yeni', PageUrls::canonical($page)->slug);
        $this->assertTrue(Url::where('page_id', $page->id)->where('slug', 'ozel-tedavi')->exists());
        $this->assertSame(2, Url::count());
    }

    public function test_page_create_rejects_conflicting_reserved_and_unsafe_paths(): void
    {
        $page = Page::create(['title' => ['tr' => 'Eski']]);
        Url::create(['page_id' => $page->id, 'slug' => '/used']);
        foreach (['used', 'USED', 'tr', 'en/foo', 'cerez-politikasi', 'kvkk-ve-gizlilik', 'appointment-request',
            'sitemap.xml', 'https://example.test', '//example.test', '../escape', 'a?x=1', 'a#b', 'a%2fb', 'a\\b', 'a//b'] as $slug) {
            Livewire::test(CreatePage::class)->fillForm(['title' => ['tr' => 'Yeni'], 'slug' => $slug])
                ->call('create')->assertHasFormErrors(['slug']);
        }
        $this->assertSame(1, Page::count());
        $this->assertSame(1, Url::count());
    }

    public function test_page_edit_rejects_another_page_alias_and_retains_existing_aliases(): void
    {
        $page = Page::create(['title' => ['tr' => 'Sayfa']]);
        $other = Page::create(['title' => ['tr' => 'Diğer']]);
        Url::create(['page_id' => $page->id, 'slug' => 'original']);
        Url::create(['page_id' => $page->id, 'slug' => 'alias']);
        Url::create(['page_id' => $other->id, 'slug' => 'other']);
        foreach (['other', 'alias', 'kvkk-ve-gizlilik', 'a?query'] as $slug) {
            Livewire::test(EditPage::class, ['record' => $page->id])->fillForm(['slug' => $slug, 'title.tr' => 'Unsaved'])
                ->call('save')->assertHasFormErrors(['slug']);
        }
        $this->assertSame('Sayfa', $page->fresh()->title['tr']);
        Livewire::test(EditPage::class, ['record' => $page->id])->fillForm(['slug' => 'new'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('new', PageUrls::canonical($page)->slug);
        $this->assertSame(['alias', 'new', 'original'], Url::where('page_id', $page->id)->orderBy('slug')->pluck('slug')->all());
    }

    public function test_page_and_media_database_changes_roll_back_when_url_persistence_fails(): void
    {
        $page = Page::create(['title' => ['tr' => 'Original']]);
        Url::create(['page_id' => $page->id, 'slug' => 'original']);
        Page::updated(function (Page $record): void {
            DB::table('media')->insert([
                'id' => (string) Str::ulid(),
                'model_type' => Page::class, 'model_id' => $record->id,
                'collection_name' => 'page_images', 'name' => 'marker', 'file_name' => 'marker.jpg',
                'disk' => 'public', 'size' => 0, 'manipulations' => '{}',
                'custom_properties' => '{}', 'generated_conversions' => '{}', 'responsive_images' => '{}',
            ]);
        });
        Url::creating(function (): void {
            throw ValidationException::withMessages(['data.slug' => 'URL write failed']);
        });
        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title.tr' => 'Changed', 'slug' => 'changed'])
            ->call('save')->assertHasFormErrors(['slug']);
        $this->assertSame('Original', $page->fresh()->title['tr']);
        $this->assertSame('original', PageUrls::canonical($page)->slug);
        $this->assertSame(0, DB::table('media')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_page_create_title_suggests_slug_and_manual_slug_is_preserved(): void
    {
        Livewire::test(CreatePage::class)->fillForm(['title.tr' => 'Özel Tedavi'])
            ->assertSchemaStateSet(['slug' => 'ozel-tedavi'], 'form')
            ->fillForm(['slug' => 'manual', 'title.tr' => 'Başka Başlık'])
            ->assertSchemaStateSet(['slug' => 'manual'], 'form')
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('manual', Url::firstOrFail()->slug);
    }

    public function test_home_sections_save_and_reject_duplicates_unknown_and_missing_sections(): void
    {
        $rows = array_map(fn ($id) => ['section' => $id, 'enabled' => $id !== 'appointment'], array_reverse(HomePageSettings::SECTIONS));
        Livewire::test(HomePageSettings::class)->set('data.home_sections', $rows)->call('save')->assertHasNoErrors();
        $this->assertSame($rows, Setting::where('key', 'home_sections')->firstOrFail()->value);
        foreach (['duplicate', 'unknown', 'missing', 'visibility'] as $kind) {
            $invalid = $rows;
            if ($kind === 'missing') {
                array_pop($invalid);
            } elseif ($kind === 'visibility') {
                $invalid[0]['enabled'] = 'not-a-boolean';
            } else {
                $invalid[0]['section'] = $kind === 'duplicate' ? $invalid[1]['section'] : '../../evil';
            }
            Livewire::test(HomePageSettings::class)->set('data.home_sections', $invalid)->call('save')->assertHasErrors();
            $this->assertSame($rows, Setting::where('key', 'home_sections')->firstOrFail()->value);
        }
    }

    public function test_legal_settings_save_bilingual_content_and_authorization(): void
    {
        Livewire::test(LegalPageSettings::class)
            ->set('data.legal_cookies_content.tr', '<p>Çerez</p>')
            ->set('data.legal_cookies_content.en', '<p>Cookies</p>')
            ->set('data.legal_privacy_content.tr', '<p>Gizlilik</p>')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(['tr' => '<p>Çerez</p>', 'en' => '<p>Cookies</p>'], Setting::where('key', 'legal_cookies_content')->firstOrFail()->value);
        $this->login(false);
        $this->assertFalse(LegalPageSettings::canAccess());
        Livewire::test(LegalPageSettings::class)->assertForbidden();
        Livewire::test(HomePageSettings::class)->assertForbidden();
    }

    public function test_nested_sidebar_can_be_managed_and_removed_without_implicit_conversion(): void
    {
        $parent = $this->menu();
        $child = $this->menu(['parent_id' => $parent->id]);
        DB::table('menus')->whereIn('id', [$parent->id, $child->id])->update(['type' => 'sidebar']);
        Livewire::test(EditMenu::class, ['record' => $child->id])
            ->fillForm(['title.tr' => 'Legacy title'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('sidebar', $child->fresh()->type);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        Livewire::test(ListMenus::class)->assertCanSeeTableRecords([$parent, $child])
            ->assertSee(__('cms.menu_not_displayed'));
        Livewire::test(EditMenu::class, ['record' => $child->id])->callAction('delete');
        $this->assertTrue(Menu::withTrashed()->findOrFail($child->id)->trashed());
        $this->assertSame('sidebar', $parent->fresh()->type);
    }

    public function test_legal_settings_roll_back_partial_writes_and_reject_oversized_content(): void
    {
        Setting::create(['key' => 'legal_cookies_title', 'value' => ['tr' => 'Original', 'en' => 'Original']]);
        $component = Livewire::test(LegalPageSettings::class);
        Setting::saving(function (Setting $setting): void {
            if ($setting->key === 'legal_privacy_content') {
                throw ValidationException::withMessages(['data.legal_privacy_content.tr' => 'Write failed']);
            }
        });
        $component->set('data.legal_cookies_title.tr', 'Changed')
            ->set('data.legal_privacy_content.tr', '<p>Changed</p>')
            ->call('save')->assertHasErrors(['data.legal_privacy_content.tr']);
        $this->assertSame('Original', Setting::where('key', 'legal_cookies_title')->firstOrFail()->value['tr']);
        $this->assertFalse(Setting::where('key', 'legal_privacy_content')->exists());
        $this->assertSame(0, DB::transactionLevel());
        Livewire::test(LegalPageSettings::class)->set('data.legal_cookies_content.tr', str_repeat('x', 100001))
            ->call('save')->assertHasErrors(['data.legal_cookies_content.tr']);
    }

    private function menu(array $attributes = []): Menu
    {
        return Menu::create($attributes + ['type' => 'header', 'title' => ['tr' => 'Menü'], 'order' => 0]);
    }

    private function login(bool $admin): void
    {
        /** @var User&MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => '01J00000000000000000000000', 'name' => 'Admin', 'email' => 'admin@example.test']);
        $user->shouldReceive('hasRole')->andReturn($admin);
        $user->shouldReceive('canAccessPanel')->andReturn(true);
        Auth::guard()->setUser($user);
    }
}
