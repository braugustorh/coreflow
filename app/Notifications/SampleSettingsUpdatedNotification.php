<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as LaravelNotification;

class SampleSettingsUpdatedNotification extends LaravelNotification
{
    use Queueable;

    public function __construct(
        public string $userName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('⚙️ Parámetros de Muestreo Actualizados')
            ->body("El usuario {$this->userName} actualizó los límites de peso de muestras, costales y factores de estimación.")
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('info')
            ->actions([
                Action::make('ver_config')
                    ->label('Ver Configuración')
                    ->button()
                    ->url('/admin/samples-settings'),
            ])
            ->getDatabaseMessage();
    }
}
