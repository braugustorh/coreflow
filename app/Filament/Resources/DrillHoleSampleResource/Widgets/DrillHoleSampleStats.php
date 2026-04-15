<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Widgets;

use App\Models\DrillHoleSample;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DrillHoleSampleStats extends BaseWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $userId = auth()->id();
        
        // Base query for user's drafts
        $drafts = DrillHoleSample::where('user_id', $userId)->where('status', 'draft');
        
        $total = (clone $drafts)->count();
        $errorsCount = (clone $drafts)->whereNotNull('errors')->count();
        $originalsCount = (clone $drafts)->whereRaw('UPPER(TRIM(sample_type)) = ?', ['O'])->count();
        $controlsCount = (clone $drafts)->whereRaw('UPPER(TRIM(sample_type)) = ?', ['CONTROL'])->count();

        return [
            Stat::make('Total de Muestras (Borrador)', $total)
                ->description('Muestras cargadas sin oficializar')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary'),
                
            Stat::make('Muestras con Errores', $errorsCount)
                ->description($errorsCount > 0 ? 'Requieren tu atención antes de acreditar' : 'Todo correcto')
                ->descriptionIcon($errorsCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->color($errorsCount > 0 ? 'danger' : 'success'),
                
            Stat::make('Muestras Originales', $originalsCount)
                ->description("Tipo 'O'")
                ->descriptionIcon('heroicon-m-beaker')
                ->color('info'),
                
            Stat::make('Muestras de Control', $controlsCount)
                ->description("Tipo 'Control'")
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info'),
        ];
    }
}
