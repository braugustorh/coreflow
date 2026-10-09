<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as LaravelNotification;

class WorkOrderDispatchedNotification extends LaravelNotification
{
    use Queueable;

    public function __construct(
        public string $workOrderCode,
        public int $samplesCount,
        public string $dispatcherName,
        public bool $includeMonitorAction = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title("🚚 WO Despachada: {$this->workOrderCode}")
            ->body("La orden {$this->workOrderCode} con {$this->samplesCount} muestra(s) fue despachada al laboratorio por {$this->dispatcherName}.")
            ->icon('heroicon-o-truck')
            ->color('success');

        if ($this->includeMonitorAction) {
            $notification->actions([
                Action::make('ver_monitor')
                    ->label('Ver en Monitor')
                    ->button()
                    ->url('/admin/sampling-monitors'),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
