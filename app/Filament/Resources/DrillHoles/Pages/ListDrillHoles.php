<?php

namespace App\Filament\Resources\DrillHoles\Pages;

use App\Filament\Resources\DrillHoles\DrillHoleResource;
use App\Models\DrillHole;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Notifications\BulkImportSummaryNotification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListDrillHoles extends ListRecords
{
    protected static string $resource = DrillHoleResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(DrillHole::count()),

            'operativos' => Tab::make('Operativos (En Proceso)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_historical', false))
                ->badge(DrillHole::where('is_historical', false)->count()),

            'historicos' => Tab::make('Históricos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_historical', true))
                ->badge(DrillHole::where('is_historical', true)->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('download_template')
                ->label('Descargar Plantilla')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    return response()->streamDownload(function () {
                        echo "\xEF\xBB\xBF";
                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['nombre_barreno', 'distrito', 'proyecto', 'es_hijo', 'barreno_madre']);
                        fputcsv($output, ['DDH-001', 'Media Luna', 'Media Luna East', 'NO', '']);
                        fputcsv($output, ['DDH-002', 'Media Luna', 'Media Luna East', 'NO', '']);
                        fputcsv($output, ['DDH-001-W1', 'Media Luna', 'Media Luna East', 'SI', 'DDH-001']);
                        fclose($output);
                    }, 'plantilla_barrenos.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Action::make('import_drill_holes')
                ->label('Carga Masiva de Barrenos')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(function (): bool {
                    $user = auth()->user();
                    if (!$user || $user->hasRole('Visor')) {
                        return false;
                    }
                    return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor Coreshack', 'Geologo'])
                        || $user->can('Create:DrillHole');
                })
                ->modalHeading('Carga Masiva de Barrenos Históricos')
                ->modalDescription('Cargue un archivo CSV o Excel con los barrenos históricos. Se clasificarán como Históricos para validación y estarán disponibles en el sistema con su jerarquía madre/hijo.')
                ->modalSubmitActionLabel('Procesar e Importar')
                ->form([
                    FileUpload::make('file')
                        ->label('Archivo CSV o Excel (.xlsx, .csv)')
                        ->disk('local')
                        ->directory('imports/drill_holes')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),

                    Select::make('fallback_sede_id')
                        ->label('Distrito por Defecto (Opcional)')
                        ->helperText('Se usará si la fila no especifica distrito.')
                        ->relationship('sede', 'name')
                        ->default(fn () => auth()->user()?->sede_id ?? Sede::first()?->id)
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Select::make('fallback_proyecto_id')
                        ->label('Proyecto por Defecto (Opcional)')
                        ->helperText('Se usará si la fila no especifica proyecto.')
                        ->relationship('proyecto', 'nombre')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ])
                ->action(function (array $data) {
                    try {
                    $filePath = Storage::disk('local')->path($data['file']);
                    if (!file_exists($filePath) || !is_readable($filePath)) {
                        Notification::make()->title('No se pudo leer el archivo cargado.')->danger()->send();
                        return;
                    }

                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $rawRows = [];

                    if (in_array($ext, ['xlsx', 'xls'])) {
                        $spreadsheet = IOFactory::load($filePath);
                        $sheet = $spreadsheet->getActiveSheet();
                        $sheetData = $sheet->toArray(null, true, true, false);

                        if (!empty($sheetData)) {
                            $header = array_shift($sheetData);
                            $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
                            foreach ($sheetData as $row) {
                                if (array_filter($row) === []) continue;
                                $rawRows[] = array_combine($header, array_pad($row, count($header), null));
                            }
                        }
                    } else {
                        $handle = fopen($filePath, 'r');
                        $header = fgetcsv($handle);
                        if ($header) {
                            $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                            if (count($header) === 1 && str_contains($header[0], ';')) {
                                rewind($handle);
                                $header = fgetcsv($handle, 0, ';');
                                $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                                $delimiter = ';';
                            } else {
                                $delimiter = ',';
                            }
                            $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

                            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                                if (array_filter($row) === []) continue;
                                $rawRows[] = array_combine($header, array_pad($row, count($header), null));
                            }
                        }
                        fclose($handle);
                    }

                    if (empty($rawRows)) {
                        Notification::make()->title('El archivo no contiene filas de datos.')->warning()->send();
                        return;
                    }

                    // Identificar nombres de columnas
                    $nameKey = null;
                    foreach (['nombre_barreno', 'barreno', 'bhid', 'id_barreno', 'hole_id'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $nameKey = $c;
                            break;
                        }
                    }

                    if (!$nameKey) {
                        Notification::make()
                            ->title('Formato incorrecto')
                            ->body('El archivo debe incluir la columna "nombre_barreno" o "barreno".')
                            ->danger()
                            ->send();
                        return;
                    }

                    $sedeKey = null;
                    foreach (['distrito', 'sede', 'distrito_minero', 'sede_id'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $sedeKey = $c;
                            break;
                        }
                    }

                    $proyectoKey = null;
                    foreach (['proyecto', 'project', 'proyecto_nombre', 'proyecto_id'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $proyectoKey = $c;
                            break;
                        }
                    }

                    $isChildKey = null;
                    foreach (['es_hijo', 'is_child', 'hijo', 'es_wedge'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $isChildKey = $c;
                            break;
                        }
                    }

                    $parentKey = null;
                    foreach (['barreno_madre', 'madre', 'parent', 'parent_id', 'barreno_padre'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $parentKey = $c;
                            break;
                        }
                    }

                    $sedesMap = Sede::all()->keyBy(fn($s) => mb_strtolower(trim($s->name)));
                    $proyectosMap = Proyecto::all()->keyBy(fn($p) => mb_strtolower(trim($p->nombre)));

                    $fallbackSedeId = $data['fallback_sede_id'] ?? null;
                    $fallbackProyectoId = $data['fallback_proyecto_id'] ?? null;

                    $processedRows = [];
                    $batchNames = [];
                    $duplicateInBatch = 0;

                    foreach ($rawRows as $row) {
                        $name = trim((string)($row[$nameKey] ?? ''));
                        if ($name === '') {
                            continue;
                        }

                        $upper = mb_strtoupper($name);
                        if (isset($batchNames[$upper])) {
                            $duplicateInBatch++;
                            continue;
                        }
                        $batchNames[$upper] = true;

                        // Determinar sede
                        $sedeId = $fallbackSedeId;
                        if ($sedeKey && !empty($row[$sedeKey])) {
                            $sVal = mb_strtolower(trim((string)$row[$sedeKey]));
                            if (isset($sedesMap[$sVal])) {
                                $sedeId = $sedesMap[$sVal]->id;
                            } elseif (is_numeric($row[$sedeKey]) && Sede::where('id', $row[$sedeKey])->exists()) {
                                $sedeId = (int)$row[$sedeKey];
                            }
                        }

                        // Determinar proyecto
                        $proyectoId = $fallbackProyectoId;
                        if ($proyectoKey && !empty($row[$proyectoKey])) {
                            $pVal = mb_strtolower(trim((string)$row[$proyectoKey]));
                            if (isset($proyectosMap[$pVal])) {
                                $proyectoId = $proyectosMap[$pVal]->id;
                            } elseif (is_numeric($row[$proyectoKey]) && Proyecto::where('id', $row[$proyectoKey])->exists()) {
                                $proyectoId = (int)$row[$proyectoKey];
                            }
                        }

                        // Determinar si es hijo
                        $isChild = false;
                        if ($isChildKey && !empty($row[$isChildKey])) {
                            $val = mb_strtolower(trim((string)$row[$isChildKey]));
                            $isChild = in_array($val, ['si', 'sí', 'yes', 'true', '1', 'y', 's']);
                        }

                        $parentName = $parentKey ? trim((string)($row[$parentKey] ?? '')) : null;
                        if (!empty($parentName)) {
                            $isChild = true;
                        }

                        $processedRows[] = [
                            'nombre_barreno' => $name,
                            'sede_id'        => $sedeId,
                            'proyecto_id'    => $proyectoId,
                            'is_child'       => $isChild,
                            'parent_name'    => !empty($parentName) ? $parentName : null,
                        ];
                    }

                    if (empty($processedRows)) {
                        Notification::make()->title('No se encontraron registros válidos de barrenos.')->warning()->send();
                        return;
                    }

                    // Verificar unicidad contra drill_holes existentes
                    $allNames = collect($processedRows)->pluck('nombre_barreno')->toArray();
                    $existingDbNames = DrillHole::whereIn('nombre_barreno', $allNames)
                        ->pluck('nombre_barreno')
                        ->map(fn($n) => mb_strtoupper($n))
                        ->flip()
                        ->toArray();

                    $toProcess = [];
                    $alreadyExistsCount = 0;

                    foreach ($processedRows as $r) {
                        $up = mb_strtoupper($r['nombre_barreno']);
                        if (isset($existingDbNames[$up])) {
                            $alreadyExistsCount++;
                        } else {
                            $toProcess[] = $r;
                        }
                    }

                    if (empty($toProcess)) {
                        Notification::make()
                            ->title('Todos los barrenos del archivo ya existen en el sistema.')
                            ->warning()
                            ->send();
                        return;
                    }

                    // Separar en Pase 1 (Madres / Principales) y Pase 2 (Hijos)
                    $pass1Parents = [];
                    $pass2Children = [];

                    foreach ($toProcess as $r) {
                        if ($r['is_child'] && !empty($r['parent_name'])) {
                            $pass2Children[] = $r;
                        } else {
                            $pass1Parents[] = $r;
                        }
                    }

                    $importedCount = 0;

                    DB::transaction(function () use ($pass1Parents, $pass2Children, &$importedCount) {
                        // Pase 1: Crear Barrenos Madre / Principales
                        foreach ($pass1Parents as $row) {
                            DrillHole::create([
                                'nombre_barreno' => $row['nombre_barreno'],
                                'sede_id'        => $row['sede_id'],
                                'proyecto_id'    => $row['proyecto_id'],
                                'is_child'       => false,
                                'parent_id'      => null,
                                'is_historical'  => true,
                                'status'         => 'historical',
                            ]);
                            $importedCount++;
                        }

                        // Pase 2: Crear Barrenos Hijos vinculando con parent_id
                        foreach ($pass2Children as $row) {
                            $parentHole = DrillHole::where('nombre_barreno', $row['parent_name'])->first();

                            DrillHole::create([
                                'nombre_barreno' => $row['nombre_barreno'],
                                'sede_id'        => $row['sede_id'] ?? $parentHole?->sede_id,
                                'proyecto_id'    => $row['proyecto_id'] ?? $parentHole?->proyecto_id,
                                'is_child'       => true,
                                'parent_id'      => $parentHole?->id,
                                'is_historical'  => true,
                                'status'         => 'historical',
                            ]);
                            $importedCount++;
                        }
                    });

                    $skippedTotal = $alreadyExistsCount + $duplicateInBatch;

                    // Enviar notificación a la campana (usuario que importó + administradores)
                    $importUsers = User::where(function ($q) {
                        $q->whereHas('roles', fn($rq) => $rq->whereIn('name', ['super_admin', 'Admin CoreFlow', 'Administrador', 'Admin']))
                          ->orWhere('id', auth()->id());
                    })->get()->unique('id');

                    if ($importUsers->isNotEmpty()) {
                        FacadesNotification::send(
                            $importUsers,
                            new BulkImportSummaryNotification(
                                'Barrenos',
                                $importedCount,
                                $skippedTotal,
                                '/admin/drill-holes'
                            )
                        );
                    }

                    $this->dispatch('databaseNotificationsSent');

                    Notification::make()
                        ->title("Carga de Barrenos completada")
                        ->body("Se importaron **{$importedCount}** barrenos históricos. " .
                            ($alreadyExistsCount > 0 ? "Se omitieron **{$alreadyExistsCount}** que ya existían. " : '') .
                            ($duplicateInBatch > 0 ? "Se omitieron **{$duplicateInBatch}** repetidos en el archivo." : ''))
                        ->success()
                        ->persistent()
                        ->send();
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('[ImportDrillHoles] Error inesperado: ' . $e->getMessage(), [
                            'file'  => $e->getFile(),
                            'line'  => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        Notification::make()
                            ->title('Error al procesar el archivo')
                            ->body('Ocurrió un error inesperado: ' . $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
