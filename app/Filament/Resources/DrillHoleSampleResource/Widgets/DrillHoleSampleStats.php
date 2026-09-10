<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Widgets;

use App\Models\DrillHoleSample;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DrillHoleSampleStats extends BaseWidget
{
    protected ?string $pollingInterval = '10s';

    public ?int $barrenoId = null;

    public function mount(?int $barrenoId = null): void
    {
        if ($barrenoId) {
            $this->barrenoId = $barrenoId;
        }
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $isAdmin = $user && ($user->hasRole(['super_admin', 'Admin CoreFlow']) || $user->id === 1);
        $isSupervisor = $user && $user->hasRole(['Supervisor CoreS', 'Supervisor Coreshack', 'supervisor', 'Supervisor']);

        // Base query para borradores
        $drafts = DrillHoleSample::where('status', 'draft')
            ->where('capture_source', 'import');

        if ($this->barrenoId) {
            $drafts->where('barreno_id', $this->barrenoId);
        } else {
            if (!$isAdmin) {
                if ($isSupervisor) {
                    $drafts->whereHas('proyecto', fn($q) => $q->where('sede_id', $user->sede_id));
                } else {
                    $drafts->where('user_id', $user?->id);
                }
            }
        }
        
        $total = (clone $drafts)->count();
        $errorsCount = (clone $drafts)->whereNotNull('errors')->count();
        $originalsCount = (clone $drafts)->whereRaw('UPPER(TRIM(sample_type)) = ?', ['O'])->count();
        $controlsCount = (clone $drafts)->whereRaw('UPPER(TRIM(sample_type)) = ?', ['CONTROL'])->count();

        $sub = $this->barrenoId ? 'del barreno actual' : 'en la bandeja';

        return [
            Stat::make('Total de Muestras (Borrador)', $total)
                ->description("Muestras {$sub}")
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
