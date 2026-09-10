<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as LaravelNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Actions\Action;

class GapValidationRequiredNotification extends LaravelNotification
{
    use Queueable;

    public function __construct(
        public string $barrenoName,
        public float $fromDepth,
        public float $toDepth,
        public float $diff
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("⚠️ GAP por Validar: Barreno {$this->barrenoName}")
            ->body("Se detectó un tramo sin información de {$this->fromDepth}m a {$this->toDepth}m ({$this->diff}m). Requiere que el Administrador o Supervisor Coreshack asigne la razón en la sección de Barrenos.")
            ->warning()
            ->actions([
                Action::make('validar')
                    ->label('Ir a Validar Barreno')
                    ->button()
                    ->url('/admin/drill-holes')
            ])
            ->getDatabaseMessage();
    }
}
