<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Services\PageUrls;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditPage extends EditRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    private string $pageSlug;

    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['slug'] = PageUrls::canonical($this->record)?->slug
            ?? Str::slug($this->record->display_title);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pageSlug = $data['slug'];
        unset($data['slug']);

        return $data;
    }

    protected function afterSave(): void
    {
        PageUrls::save($this->record, $this->pageSlug);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
