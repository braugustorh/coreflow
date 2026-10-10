<?php

namespace App\Filament\Actions;

use App\Models\DrillHoleSample;
use Filament\Actions\Action;

class QcPhotosAction
{
    public static function make(): Action
    {
        return Action::make('qcPhotos')
            ->label(function (DrillHoleSample $record): string {
                $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                return "Fotos ({$count}/2)";
            })
            ->icon('heroicon-o-camera')
            ->color(function (DrillHoleSample $record): string {
                return $record->hasCompleteQcPhotos() ? 'success' : 'warning';
            })
            ->visible(function (DrillHoleSample $record): bool {
                return $record->requiresQcPhotos();
            })
            ->slideOver()
            ->modalHeading(fn (DrillHoleSample $record) => "Evidencia Fotográfica QC · Muestra {$record->sample_number}")
            ->modalContent(fn (DrillHoleSample $record) => view('filament.components.qc-photo-modal', ['record' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }
}
