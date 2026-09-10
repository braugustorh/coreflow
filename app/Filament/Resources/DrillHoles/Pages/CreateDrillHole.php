<?php

namespace App\Filament\Resources\DrillHoles\Pages;

use App\Filament\Resources\DrillHoles\DrillHoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDrillHole extends CreateRecord
{
    protected static string $resource = DrillHoleResource::class;

    /**
     * Redirige a la lista tras crear.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Notificación de éxito personalizada al crear un barreno.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->icon('heroicon-o-check-badge')
            ->title('Barreno registrado correctamente')
            ->body('El barreno **' . $this->record->nombre_barreno . '** ha sido creado exitosamente.');
    }
}
