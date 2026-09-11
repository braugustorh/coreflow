<?php

namespace App\Filament\Widgets;

use App\Models\DrillHole;
use App\Models\WorkOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class DistrictStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $sedeId = $this->filters['sede_id'] ?? auth()->user()->sede_id;
        $proyectoId = $this->filters['proyecto_id'] ?? null;

        if (!$sedeId) {
            return [];
        }

        // 1. Barrenos en Borrador (DrillHoles con muestras en draft en esta sede/proyecto)
        $barrenosBorrador = DrillHole::where('sede_id', $sedeId)
            ->when($proyectoId, fn (Builder $query) => $query->where('proyecto_id', $proyectoId))
            ->whereHas('drillHoleSamples', function (Builder $q) {
                $q->where('status', 'draft');
            })
            ->count();

        // 2. Work Orders
        $woQuery = WorkOrder::where('sede_id', $sedeId)
            ->when($proyectoId, function (Builder $query) use ($proyectoId) {
                $query->whereHas('drillHoleSamples', function ($q) use ($proyectoId) {
                    $q->where('proyecto_id', $proyectoId);
                });
            });
        
        $woAsignadas = (clone $woQuery)->count();
        $woEnviadas = (clone $woQuery)->whereNotNull('dispatch_date')->whereNull('reception_date')->count();
        $woRecibidas = (clone $woQuery)->whereNotNull('reception_date')->count();

        return [
            Stat::make('Barrenos en Borrador', $barrenosBorrador)
                ->description('Con muestras por validar')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Work Orders Asignadas', $woAsignadas)
                ->description('Total generadas')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Work Orders Enviadas', $woEnviadas)
                ->description('En tránsito al laboratorio')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Work Orders Recibidas', $woRecibidas)
                ->description('Procesadas por laboratorio')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
