<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class NotificationDiagnostic extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Diagnóstico Alertas';
    protected static ?string $title = 'Módulo de Diagnóstico de Notificaciones (Toast)';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 100;
    protected string $view = 'filament.pages.notification-diagnostic';

    public ?string $lastActionMessage = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow']) || $user->can('View:NotificationDiagnostic');
    }

    /**
     * Test 1: Notificación Simple Oficial de Filament
     */
    public function testSimpleNotification(): void
    {
        Notification::make()
            ->title('Alerta Simple Exitosa')
            ->body('Esta es una notificación estándar de Filament enviada con send().')
            ->success()
            ->send();

        $this->lastActionMessage = 'Test 1 ejecutado: Notification::make()->success()->send()';
    }

    /**
     * Test 2: Notificación con Botón de Acción (Igual que en Captura Ágil)
     */
    public function testNotificationWithButton(): void
    {
        Notification::make()
            ->title('Barreno no encontrado (Simulación)')
            ->body('El barreno ingresado no existe en los registros.')
            ->danger()
            ->actions([
                Action::make('crear')
                    ->label('Crear Barreno')
                    ->url(\App\Filament\Resources\DrillHoles\DrillHoleResource::getUrl('index'))
                    ->button(),
            ])
            ->send();

        $this->lastActionMessage = 'Test 2 ejecutado: Notification con botón de acción enviado con send()';
    }

    /**
     * Test 2B: Notificación con Botón enviada por DISPATCH DIRECTO (Sin Cookie)
     */
    public function testNotificationWithButtonDirect(): void
    {
        $notification = Notification::make()
            ->title('Barreno no encontrado (Bypass Cookie)')
            ->body('El barreno ingresado no existe en los registros.')
            ->danger()
            ->actions([
                Action::make('crear')
                    ->label('Crear Barreno')
                    ->url(\App\Filament\Resources\DrillHoles\DrillHoleResource::getUrl('index'))
                    ->button(),
            ]);

        $this->dispatch('notificationSent', notification: $notification->toArray());

        $this->lastActionMessage = 'Test 2B ejecutado: Notificación con botón enviada DIRECTO vía $this->dispatch("notificationSent"). CERO bytes en Cookie.';
    }

    public function getSessionDetailsProperty(): array
    {
        $all = session()->all();
        $items = [];
        $totalRaw = 0;

        foreach ($all as $k => $v) {
            $ser = serialize($v);
            $bytes = strlen($ser);
            $totalRaw += $bytes;
            $items[] = [
                'key' => $k,
                'bytes' => $bytes,
                'preview' => is_string($v) ? Str::limit($v, 40) : (is_array($v) ? 'Array (' . count($v) . ' items)' : gettype($v)),
            ];
        }

        $estimatedEncrypted = $totalRaw > 0 ? strlen(app('encrypter')->encrypt(serialize($all))) : 0;

        return [
            'total_raw' => $totalRaw,
            'estimated_encrypted' => $estimatedEncrypted,
            'is_overflow' => $estimatedEncrypted >= 4000,
            'items' => $items,
        ];
    }

    public function clearSession(): void
    {
        session()->forget('filament.notifications');
        $this->lastActionMessage = 'Se limpiaron las notificaciones acumuladas en sesión.';
    }

    /**
     * Test 3: Notificación de Advertencia (Warning)
     */
    public function testWarningNotification(): void
    {
        Notification::make()
            ->title('Advertencia de Prueba')
            ->body('Esta es una notificación de prueba de tipo advertencia.')
            ->warning()
            ->send();

        $this->lastActionMessage = 'Test 3 ejecutado: Notification::make()->warning()->send()';
    }

    /**
     * Test 4: Notificación a Base de Datos (Campana del Topbar)
     */
    public function testDatabaseNotification(): void
    {
        $user = auth()->user();

        if ($user) {
            Notification::make()
                ->title('Notificación en Base de Datos')
                ->body('Esta notificación se guardó directamente en la campana de alertas.')
                ->info()
                ->sendToDatabase($user);

            $this->lastActionMessage = 'Test 4 ejecutado: Notificación guardada en base de datos para ' . $user->name;
        } else {
            $this->lastActionMessage = 'Test 4 error: No hay usuario autenticado.';
        }
    }

    /**
     * Test 5: Disparo Directo de Evento Livewire (Bypass de Sesión)
     */
    public function testDirectLivewireEvent(): void
    {
        $this->dispatch('notificationSent', notification: [
            'id' => (string) Str::uuid(),
            'title' => 'Bypass Sesión (Livewire Dispatch)',
            'body' => 'Esta alerta se envió vía $this->dispatch("notificationSent"). No depende de session().',
            'status' => 'success',
            'color' => 'success',
            'duration' => 6000,
        ]);

        $this->lastActionMessage = 'Test 5 ejecutado: $this->dispatch("notificationSent", ...) directo.';
    }

    /**
     * Test 6: Inyección Manual en Sesión + Dispatch de notificationsSent
     */
    public function testManualSessionPush(): void
    {
        $notification = Notification::make()
            ->title('Manual Session + notificationsSent')
            ->body('Guardado con session()->push y disparado manualmente.')
            ->info();

        session()->push('filament.notifications', $notification->toArray());

        $this->dispatch('notificationsSent');

        $this->lastActionMessage = 'Test 6 ejecutado: session()->push("filament.notifications") + $this->dispatch("notificationsSent").';
    }

    /**
     * Test 7: Disparo Directo por JavaScript / Alpine (En el Navegador)
     */
    public function testJavascriptNotification(): void
    {
        $this->js("
            if (typeof FilamentNotification !== 'undefined') {
                new FilamentNotification()
                    .title('Alerta desde JavaScript (Alpine)')
                    .body('Generada directamente en el navegador con window.FilamentNotification.')
                    .success()
                    .send();
            } else {
                alert('FilamentNotification NO está definido en window');
            }
        ");

        $this->lastActionMessage = 'Test 7 ejecutado: new FilamentNotification().send() disparado desde JS del cliente.';
    }

    /**
     * Test 8: Alerta Nativa de Navegador (alert)
     */
    public function testNativeAlert(): void
    {
        $this->js("alert('¡El puente Livewire -> JavaScript funciona correctamente en este entorno!');");

        $this->lastActionMessage = 'Test 8 ejecutado: alert() nativo en el navegador.';
    }
}
