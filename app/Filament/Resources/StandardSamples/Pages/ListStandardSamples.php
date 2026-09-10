<?php

namespace App\Filament\Resources\StandardSamples\Pages;

use App\Filament\Resources\StandardSamples\StandardSampleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStandardSamples extends ListRecords
{
    protected static string $resource = StandardSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
