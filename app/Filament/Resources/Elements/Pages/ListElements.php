<?php

namespace App\Filament\Resources\Elements\Pages;

use App\Filament\Resources\Elements\ElementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListElements extends ListRecords
{
    protected static string $resource = ElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
