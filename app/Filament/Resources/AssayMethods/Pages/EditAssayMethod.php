<?php

namespace App\Filament\Resources\AssayMethods\Pages;

use App\Filament\Resources\AssayMethods\AssayMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssayMethod extends EditRecord
{
    protected static string $resource = AssayMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
