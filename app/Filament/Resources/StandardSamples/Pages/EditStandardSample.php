<?php

namespace App\Filament\Resources\StandardSamples\Pages;

use App\Filament\Resources\StandardSamples\StandardSampleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStandardSample extends EditRecord
{
    protected static string $resource = StandardSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
