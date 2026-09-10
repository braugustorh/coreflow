<?php

namespace App\Filament\Resources\DrillHoles\Pages;

use App\Filament\Resources\DrillHoles\DrillHoleResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrillHole extends EditRecord
{
    protected static string $resource = DrillHoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successNotificationTitle('Barreno eliminado correctamente'),
        ];
    }

    /**
     * Redirige a la lista tras guardar.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Notificación de éxito personalizada al actualizar un barreno.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->icon('heroicon-o-pencil-square')
            ->title('Barreno actualizado correctamente')
            ->body('Los datos del barreno **' . $this->record->nombre_barreno . '** han sido guardados.');
    }
}
