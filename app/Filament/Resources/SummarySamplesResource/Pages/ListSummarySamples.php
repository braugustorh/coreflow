<?php

namespace App\Filament\Resources\SummarySamplesResource\Pages;

use App\Filament\Resources\SummarySamplesResource;
use Filament\Resources\Pages\ListRecords;

class ListSummarySamples extends ListRecords
{
    protected static string $resource = SummarySamplesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No requiere acción de crear individual ya que se sincronizan desde Work Orders
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
