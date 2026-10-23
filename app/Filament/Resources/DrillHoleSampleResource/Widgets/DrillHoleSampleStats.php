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

        // Desglose de tipos de errores
        $breakdownParts = [];
        if ($errorsCount > 0) {
            $samplesWithErrors = (clone $drafts)->whereNotNull('errors')->get(['errors']);
            $categories = [
                'GAPs'         => 0,
                'Traslapes'    => 0,
                'Longitud'     => 0,
                'Recup.'       => 0,
                'Pesos'        => 0,
                'Folios'       => 0,
                'Otros'        => 0,
            ];

            foreach ($samplesWithErrors as $sample) {
                $errList = (array) $sample->errors;
                $seen = [];
                foreach ($errList as $err) {
                    $u = strtoupper((string) $err);
                    if (str_contains($u, 'GAP')) {
                        $seen['GAPs'] = true;
                    } elseif (str_contains($u, 'TRASLAPE')) {
                        $seen['Traslape'] = true;
                    } elseif (str_contains($u, 'LONGITUD') || str_contains($u, 'FROM')) {
                        $seen['Longitud'] = true;
                    } elseif (str_contains($u, 'RECUPERACIÓN') || str_contains($u, 'RECUPERACION')) {
                        $seen['Recup.'] = true;
                    } elseif (str_contains($u, 'PESO') || str_contains($u, 'WEIGHT')) {
                        $seen['Pesos'] = true;
                    } elseif (str_contains($u, 'DUPLICADO') || str_contains($u, 'HISTÓRICO') || str_contains($u, 'HISTORICO') || str_contains($u, 'OFICIAL') || str_contains($u, 'CONFLICTO')) {
                        $seen['Folios'] = true;
                    } else {
                        $seen['Otros'] = true;
                    }
                }
                foreach ($seen as $cat => $val) {
                    $categories[$cat]++;
                }
            }

            foreach ($categories as $cat => $qty) {
                if ($qty > 0) {
                    $breakdownParts[] = "{$qty} {$cat}";
                }
            }
        }

        $errorDesc = !empty($breakdownParts)
            ? implode(' • ', $breakdownParts)
            : ($errorsCount > 0 ? 'Requieren tu atención antes de acreditar' : 'Todo correcto');

        $sub = $this->barrenoId ? 'del barreno actual' : 'en la bandeja';

        return [
            Stat::make('Total de Muestras (Borrador)', $total)
                ->description("Muestras {$sub}")
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary'),
                
            Stat::make('Muestras con Errores', $errorsCount)
                ->description($errorDesc)
                ->descriptionIcon($errorsCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->color($errorsCount > 0 ? 'danger' : 'success')
                ->extraAttributes($errorsCount > 0 ? ['title' => 'Tipos de error: ' . implode(', ', $breakdownParts)] : []),
                
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
