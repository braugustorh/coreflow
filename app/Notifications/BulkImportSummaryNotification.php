<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as LaravelNotification;

class BulkImportSummaryNotification extends LaravelNotification
{
    use Queueable;

    public function __construct(
        public string $resourceName,
        public int $createdCount,
        public int $skippedCount,
        public string $targetUrl
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $hasSkipped = $this->skippedCount > 0;
        $body = $hasSkipped
            ? "Se importaron {$this->createdCount} registro(s) exitosamente y se omitieron {$this->skippedCount} por duplicidad o inconsistencias."
            : "Se importaron {$this->createdCount} registro(s) exitosamente.";

        return FilamentNotification::make()
            ->title("📊 Carga Masiva: {$this->resourceName}")
            ->body($body)
            ->icon('heroicon-o-arrow-up-tray')
            ->color($hasSkipped ? 'warning' : 'success')
            ->actions([
                Action::make('ver_modulo')
                    ->label("Ver {$this->resourceName}")
                    ->button()
                    ->url($this->targetUrl),
            ])
            ->getDatabaseMessage();
    }
}
