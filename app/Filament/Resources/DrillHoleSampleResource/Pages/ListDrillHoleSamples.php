<?php

namespace App\Filament\Resources\DrillHoleSampleResource\Pages;

use App\Filament\Resources\DrillHoleSampleResource;
use App\Filament\Resources\DrillHoleSampleResource\Widgets\DrillHoleSampleStats;
use App\Models\DrillHoleSample;
use App\Services\SampleValidationService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListDrillHoleSamples extends ListRecords
{
    protected static string $resource = DrillHoleSampleResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            DrillHoleSampleStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_csv')
                ->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('Archivo CSV')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data, SampleValidationService $service) {
                    $path = Storage::disk('local')->path($data['csv_file']);
                    
                    if (!file_exists($path) || !is_readable($path)) {
                        Notification::make()->title('Error al leer el archivo.')->danger()->send();
                        return;
                    }

                    $file = fopen($path, 'r');
                    $header = fgetcsv($file);
                    
                    if (!$header) {
                        Notification::make()->title('Archivo vacío o incorrecto.')->danger()->send();
                        return;
                    }

                    // BOM removal for first column
                    $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                    
                    // Normalize headers
                    $normalizedHeaders = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $header);

                    $userId = auth()->id();
                    $importedCount = 0;

                    while (($row = fgetcsv($file)) !== false) {
                        // Skip empty rows
                        if (array_filter($row) === []) {
                            continue;
                        }
                        
                        $rowData = array_combine($normalizedHeaders, array_pad($row, count($normalizedHeaders), null));
                        
                        // Parse values based on mapped columns
                        DrillHoleSample::create([
                            'user_id' => $userId,
                            'status' => 'draft',
                            'bhid' => $rowData['bhid'] ?? null,
                            'from' => isset($rowData['from']) && trim($rowData['from']) !== '' ? (float) $rowData['from'] : null,
                            'to' => isset($rowData['to']) && trim($rowData['to']) !== '' ? (float) $rowData['to'] : null,
                            'drilled_length' => isset($rowData['drilled_length']) && trim($rowData['drilled_length']) !== '' ? (float) $rowData['drilled_length'] : null,
                            'sample_length' => isset($rowData['sample_length']) && trim($rowData['sample_length']) !== '' ? (float) $rowData['sample_length'] : null,
                            'sample_number' => $rowData['sample_number'] ?? null,
                            'sample_type' => $rowData['sample_type'] ?? null,
                            'control_type' => $rowData['control_type'] ?? null,
                            'wght' => isset($rowData['wght']) && trim($rowData['wght']) !== '' ? (float) $rowData['wght'] : null,
                            'comments' => $rowData['comments'] ?? null,
                        ]);
                        $importedCount++;
                    }
                    fclose($file);

                    // Run validations
                    $service->validateDraftsForUser($userId);

                    Notification::make()
                        ->title("Se importaron y validaron $importedCount registros.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('validate_data')
                ->label('Validar Datos')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->action(function (SampleValidationService $service) {
                    $service->validateDraftsForUser(auth()->id());
                    Notification::make()
                        ->title('Validación completada.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('officialize_data')
                ->label('Acreditar / Oficializar')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $userId = auth()->id();
                    $draftsWithErrors = DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->whereNotNull('errors')
                        ->count();

                    if ($draftsWithErrors > 0) {
                        Notification::make()
                            ->title("Hay $draftsWithErrors registros con errores. Corrige todo antes de oficializar.")
                            ->danger()
                            ->send();
                        return;
                    }

                    DrillHoleSample::where('user_id', $userId)
                        ->where('status', 'draft')
                        ->update(['status' => 'official']);

                    Notification::make()
                        ->title('Los datos han sido acreditados oficialmente.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
