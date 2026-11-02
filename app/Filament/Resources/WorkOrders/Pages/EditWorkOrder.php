<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('release_samples')
                ->label('Desasociar muestras')
                ->icon('heroicon-o-link-slash')
                ->color('warning')
                ->visible(fn (WorkOrder $record): bool => WorkOrder::userCanManageAssociations()
                    && $record->drillHoleSamples()->exists())
                ->disabled(fn (WorkOrder $record): bool => $record->getAssociationLockReason() !== null)
                ->tooltip(fn (WorkOrder $record): ?string => $record->getAssociationLockReason())
                ->requiresConfirmation()
                ->modalHeading(fn (WorkOrder $record): string => "Desasociar muestras de la WO {$record->work_order_code}")
                ->modalDescription(function (WorkOrder $record): string {
                    $count = $record->drillHoleSamples()->count();

                    return "Se desasociarán {$count} muestra(s) de esta Work Order. Las muestras NO se eliminan: conservan todos sus datos y quedan sin Work Order asignada para poder reasignarlas. La Work Order se mantiene con 0 muestras.";
                })
                ->modalSubmitActionLabel('Sí, desasociar muestras')
                ->action(function (WorkOrder $record): void {
                    if ($reason = $record->getAssociationLockReason()) {
                        Notification::make()
                            ->title('Operación no permitida')
                            ->body($reason)
                            ->danger()
                            ->send();

                        return;
                    }

                    $count = $record->releaseSamples();

                    $this->refreshFormData(['samples_quantity']);

                    Notification::make()
                        ->title('Muestras desasociadas')
                        ->body("{$count} muestra(s) quedaron sin Work Order asignada.")
                        ->success()
                        ->send();
                }),

            DeleteAction::make()
                ->visible(fn (): bool => WorkOrder::userCanManageAssociations())
                ->disabled(fn (WorkOrder $record): bool => $record->getAssociationLockReason() !== null)
                ->tooltip(fn (WorkOrder $record): ?string => $record->getAssociationLockReason())
                ->modalHeading(fn (WorkOrder $record): string => "Eliminar Work Order {$record->work_order_code}")
                ->modalDescription(function (WorkOrder $record): string {
                    $samplesCount = $record->drillHoleSamples()->count();
                    if ($samplesCount > 0) {
                        $barrenos = $record->barrenos_list;

                        return "⚠️ ATENCIÓN: Esta Work Order tiene {$samplesCount} muestra(s) asignada(s) (Barreno: {$barrenos}). Si la eliminas, la orden desaparecerá del Monitor de Muestreo y las {$samplesCount} muestras NO se borran, pero quedarán sin Work Order asignada. ¿Estás seguro de continuar?";
                    }

                    return '¿Estás seguro de que deseas eliminar esta Work Order? Esta acción no se puede deshacer.';
                })
                ->before(function (WorkOrder $record, DeleteAction $action): void {
                    if ($reason = $record->getAssociationLockReason()) {
                        Notification::make()
                            ->title('Operación no permitida')
                            ->body($reason)
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
