<?php

namespace App\Filament\Widgets;

use App\Models\Proyecto;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ActiveDrillHolesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Barrenos por Proyecto';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $sedeId = $this->filters['sede_id'] ?? auth()->user()->sede_id;
        $proyectoId = $this->filters['proyecto_id'] ?? null;

        if (!$sedeId) {
            return ['datasets' => [], 'labels' => []];
        }

            // Listar proyectos de la sede con el conteo de barrenos operativos
            $proyectos = Proyecto::where('sede_id', $sedeId)
                ->when($proyectoId, fn ($q) => $q->where('id', $proyectoId))
                ->withCount(['drillHoles' => fn ($q) => $q->where('is_historical', false)])
                ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Barrenos Activos',
                    'data' => $proyectos->pluck('drill_holes_count')->toArray(),
                    'backgroundColor' => '#f59e0b', // Color Amber (Primary de Filament)
                ],
            ],
            'labels' => $proyectos->pluck('nombre')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
