<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Services\PageUrls;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    private string $pageSlug;

    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pageSlug = $data['slug'];
        unset($data['slug']);

        return $data;
    }

    protected function afterCreate(): void
    {
        PageUrls::save($this->record, $this->pageSlug);
    }
}
