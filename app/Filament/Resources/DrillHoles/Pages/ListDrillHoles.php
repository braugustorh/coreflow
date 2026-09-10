<?php

namespace App\Filament\Resources\DrillHoles\Pages;

use App\Filament\Resources\DrillHoles\DrillHoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDrillHoles extends ListRecords
{
    protected static string $resource = DrillHoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Expande la tabla al ancho completo de la pantalla (igual que el Monitor de Muestras)
     */
    public function getMaxContentWidth(): \Filament\Support\Enums\Width | string | null
    {
        return \Filament\Support\Enums\Width::Full;
    }
}
