<?php

namespace App\Filament\Resources\AssayMethods\Pages;

use App\Filament\Resources\AssayMethods\AssayMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssayMethods extends ListRecords
{
    protected static string $resource = AssayMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
