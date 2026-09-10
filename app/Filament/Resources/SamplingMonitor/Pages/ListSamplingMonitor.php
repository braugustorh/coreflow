<?php

namespace App\Filament\Resources\SamplingMonitor\Pages;

use App\Filament\Resources\SamplingMonitor\SamplingMonitorResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSamplingMonitor extends ListRecords
{
    protected static string $resource = SamplingMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sin acción de creación; las WOs se crean desde la Captura Ágil
        ];
    }

    /**
     * Tabs para alternar entre WOs activas y archivadas.
     * La lógica filtra basándose en si las muestras de la WO tienen is_archived.
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Por Enviar')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder =>
                    $query->whereHas('drillHoleSamples', fn ($q) =>
                        $q->where('is_archived', false)
                    )
                )
                ->badge(
                    \App\Models\WorkOrder::whereHas('drillHoleSamples', fn ($q) =>
                        $q->where('is_archived', false)
                    )->count()
                )
                ->badgeColor('warning'),

            'archived' => Tab::make('Enviados')
                ->icon('heroicon-o-paper-airplane')
                ->modifyQueryUsing(fn (Builder $query): Builder =>
                    $query->whereHas('drillHoleSamples', fn ($q) =>
                        $q->where('is_archived', true)
                    )
                )
                ->badge(
                    \App\Models\WorkOrder::whereHas('drillHoleSamples', fn ($q) =>
                        $q->where('is_archived', true)
                    )->count()
                )
                ->badgeColor('success'),
        ];
    }

    /**
     * Expande la tabla al ancho completo de la pantalla
     */
    public function getMaxContentWidth(): \Filament\Support\Enums\Width | string | null
    {
        return \Filament\Support\Enums\Width::Full;
    }
}
