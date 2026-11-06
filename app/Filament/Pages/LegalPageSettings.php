<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class LegalPageSettings extends Page
{
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['Admin', 'admin', 'Developer', 'developer']) ?? false;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('physio.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms.legal_settings');
    }

    public function getTitle(): string
    {
        return __('cms.legal_settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(Setting::all()->pluck('value', 'key')->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (['cookies', 'privacy'] as $page) {
            foreach (['tr', 'en'] as $locale) {
                $tabs[] = Tabs\Tab::make($page.'_'.$locale)
                    ->label(__('cms.legal_'.$page).' ('.strtoupper($locale).')')
                    ->schema([
                        TextInput::make("legal_{$page}_title.{$locale}")->label(__('panel.common.title'))->maxLength(255),
                        TextInput::make("legal_{$page}_meta.{$locale}")->label(__('cms.meta_description'))->maxLength(500),
                        RichEditor::make("legal_{$page}_content.{$locale}")
                            ->label(__('panel.common.content'))->helperText(__('cms.legal_help'))
                            ->maxLength(100000)
                            ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'undo', 'redo']),
                    ]);
            }
        }

        return $schema->components([Tabs::make('legal')->tabs($tabs)])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label(__('panel.common.save'))->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        DB::transaction(function () use ($data): void {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'text']);
            }
        });
        foreach (array_keys($data) as $key) {
            Setting::forgetCache($key);
        }
        Notification::make()->success()->title(__('panel.common.settings_saved_successfully'))->send();
    }
}
