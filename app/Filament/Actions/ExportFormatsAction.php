<?php

namespace App\Filament\Actions;

use App\Models\WorkOrder;
use App\Services\DispatchFormatsPdfService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ExportFormatsAction
{
    /**
     * Construye la acción "Formatos" que genera y descarga el Expediente Completo
     * en PDF unificado (Página 1: Formulario ALS + Páginas siguientes: Registro de Costales).
     */
    public static function make(bool $restrictToAuthorizedRoles = false): Action
    {
        $action = Action::make('export_formats')
            ->label('Formatos')
            ->icon('heroicon-o-document-duplicate')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(fn (WorkOrder $record): string => "Descargar Formatos — WO {$record->work_order_code}")
            ->modalDescription('Se generará el Expediente Completo en un solo archivo PDF (Página 1: Formulario ALS Geochemistry + Páginas siguientes: Registro de Costales).')
            ->modalSubmitActionLabel('Descargar PDF')
            ->action(function (WorkOrder $record, DispatchFormatsPdfService $service) {
                try {
                    return $service->generateUnifiedResponse($record);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Error al generar Formatos')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return null;
                }
            });

        if ($restrictToAuthorizedRoles) {
            $action->visible(fn (): bool => WorkOrder::userCanDownloadFormats());
        }

        return $action;
    }
}
