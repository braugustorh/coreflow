<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as LaravelNotification;

class GapValidatedNotification extends LaravelNotification
{
    use Queueable;

    public function __construct(
        public string $barrenoName,
        public float $fromDepth,
        public float $toDepth,
        public string $validatorName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("✅ Tramo Liberado: Barreno {$this->barrenoName}")
            ->body("El GAP de {$this->fromDepth}m a {$this->toDepth}m fue validado y justificado por {$this->validatorName}.")
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->actions([
                Action::make('ver_barrenos')
                    ->label('Ver Barrenos')
                    ->button()
                    ->url('/admin/drill-holes'),
            ])
            ->getDatabaseMessage();
    }
}
