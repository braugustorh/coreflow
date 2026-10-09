<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Sede;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\BulkImportSummaryNotification;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(WorkOrder::count()),

            'en_proceso' => Tab::make('Activas / En Proceso')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->whereNull('status')
                      ->orWhere('status', '!=', 'Liberado');
                })->where(function ($q) {
                    $q->whereNull('comments')
                      ->orWhere('comments', 'not like', '%Histórico%');
                }))
                ->badge(WorkOrder::where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'Liberado');
                })->where(function ($q) {
                    $q->whereNull('comments')->orWhere('comments', 'not like', '%Histórico%');
                })->count()),

            'liberadas' => Tab::make('Liberadas (Plataforma)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Liberado')->where(function ($q) {
                    $q->whereNull('comments')->orWhere('comments', 'not like', '%Histórico%');
                }))
                ->badge(WorkOrder::where('status', 'Liberado')->where(function ($q) {
                    $q->whereNull('comments')->orWhere('comments', 'not like', '%Histórico%');
                })->count()),

            'historicas' => Tab::make('Históricas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('comments', 'like', '%Histórico%'))
                ->badge(WorkOrder::where('comments', 'like', '%Histórico%')->count()),
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
                        fputcsv($output, ['work_order', 'fecha', 'distrito']);
                        fputcsv($output, ['230501', '2023-05-15', 'Media Luna']);
                        fputcsv($output, ['230502', '', '']);
                        fclose($output);
                    }, 'plantilla_work_orders.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),

            Action::make('import_work_orders')
                ->label('Carga Masiva de Work Orders')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(function (): bool {
                    $user = auth()->user();
                    if (!$user || $user->hasRole('Visor')) {
                        return false;
                    }
                    return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor Coreshack'])
                        || $user->can('Create:WorkOrder');
                })
                ->modalHeading('Carga Masiva de Work Orders Históricas')
                ->modalDescription('Cargue un archivo CSV o Excel con el código de Work Order, fecha (opcional) y distrito (opcional). Los registros se guardarán con estado "Liberado" y comentario "Histórico".')
                ->modalSubmitActionLabel('Procesar e Importar')
                ->form([
                    FileUpload::make('file')
                        ->label('Archivo CSV o Excel (.xlsx, .csv)')
                        ->disk('local')
                        ->directory('imports/work_orders')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),

                    Select::make('fallback_sede_id')
                        ->label('Distrito por Defecto')
                        ->helperText('Se asignará a las órdenes de trabajo que no especifiquen distrito en el archivo.')
                        ->relationship('sede', 'name')
                        ->default(fn () => auth()->user()?->sede_id ?? Sede::first()?->id)
                        ->searchable()
                        ->preload()
                        ->required(),
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

                    // Identificar llaves
                    $woKey = null;
                    foreach (['work_order', 'work_order_code', 'wo', 'codigo_wo', 'orden_trabajo'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $woKey = $c;
                            break;
                        }
                    }

                    if (!$woKey) {
                        Notification::make()
                            ->title('Formato incorrecto')
                            ->body('El archivo debe incluir la columna "work_order" o "work_order_code".')
                            ->danger()
                            ->send();
                        return;
                    }

                    $dateKey = null;
                    foreach (['fecha', 'date', 'reception_date', 'dispatch_date', 'fecha_envio', 'fecha_recepcion'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $dateKey = $c;
                            break;
                        }
                    }

                    $sedeKey = null;
                    foreach (['distrito', 'sede', 'distrito_minero', 'sede_id'] as $c) {
                        if (array_key_exists($c, $rawRows[0] ?? [])) {
                            $sedeKey = $c;
                            break;
                        }
                    }

                    $sedesMap = Sede::all()->keyBy(fn($s) => mb_strtolower(trim($s->name)));
                    $fallbackSedeId = (int)$data['fallback_sede_id'];

                    $batchCodes = [];
                    $duplicateInBatch = 0;
                    $validRows = [];

                    foreach ($rawRows as $row) {
                        $code = trim((string)($row[$woKey] ?? ''));
                        if ($code === '') {
                            continue;
                        }

                        $upperCode = mb_strtoupper($code);
                        if (isset($batchCodes[$upperCode])) {
                            $duplicateInBatch++;
                            continue;
                        }
                        $batchCodes[$upperCode] = true;

                        // Resolver fecha
                        $parsedDate = null;
                        if ($dateKey && !empty($row[$dateKey])) {
                            try {
                                $parsedDate = Carbon::parse(trim((string)$row[$dateKey]))->format('Y-m-d');
                            } catch (\Throwable) {
                                $parsedDate = null;
                            }
                        }

                        // Resolver sede_id
                        $sedeId = $fallbackSedeId;
                        if ($sedeKey && !empty($row[$sedeKey])) {
                            $sedeVal = mb_strtolower(trim((string)$row[$sedeKey]));
                            if (isset($sedesMap[$sedeVal])) {
                                $sedeId = $sedesMap[$sedeVal]->id;
                            } elseif (is_numeric($row[$sedeKey]) && Sede::where('id', $row[$sedeKey])->exists()) {
                                $sedeId = (int)$row[$sedeKey];
                            }
                        }

                        $validRows[] = [
                            'work_order_code' => $code,
                            'sede_id'         => $sedeId,
                            'dispatch_date'   => $parsedDate,
                            'reception_date'  => $parsedDate,
                            'status'          => 'Liberado',
                            'comments'        => 'Histórico',
                            'samples_quantity' => 0,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                    }

                    if (empty($validRows)) {
                        Notification::make()->title('No se encontraron registros válidos para importar.')->warning()->send();
                        return;
                    }

                    // Verificar duplicados contra work_orders existentes
                    $allCodes = collect($validRows)->pluck('work_order_code')->toArray();
                    $existingInDb = WorkOrder::whereIn('work_order_code', $allCodes)
                        ->pluck('work_order_code')
                        ->map(fn($c) => mb_strtoupper($c))
                        ->flip()
                        ->toArray();

                    $toInsert = [];
                    $alreadyExistsCount = 0;

                    foreach ($validRows as $r) {
                        $up = mb_strtoupper($r['work_order_code']);
                        if (isset($existingInDb[$up])) {
                            $alreadyExistsCount++;
                        } else {
                            $toInsert[] = $r;
                        }
                    }

                    $importedCount = 0;
                    if (!empty($toInsert)) {
                        DB::transaction(function () use ($toInsert, &$importedCount) {
                            foreach (array_chunk($toInsert, 1000) as $chunk) {
                                WorkOrder::insert($chunk);
                                $importedCount += count($chunk);
                            }
                        });
                    }

                    Notification::make()
                        ->title("Carga de Work Orders completada")
                        ->body("Se importaron **{$importedCount}** órdenes de trabajo como *Liberado / Histórico*. " .
                            ($alreadyExistsCount > 0 ? "Se omitieron **{$alreadyExistsCount}** que ya existían previamente en la base de datos. " : '') .
                            ($duplicateInBatch > 0 ? "Se omitieron **{$duplicateInBatch}** repetidas en el archivo." : ''))
                        ->success()
                        ->persistent()
                        ->send();

                    // Notificación persistente a campana
                    $importUsers = User::where(function ($q) {
                        $q->whereHas('roles', fn($rq) => $rq->whereIn('name', ['super_admin', 'Admin CoreFlow', 'Administrador', 'Admin']))
                          ->orWhere('id', auth()->id());
                    })->get()->unique('id');

                    if ($importUsers->isNotEmpty()) {
                        FacadesNotification::send(
                            $importUsers,
                            new BulkImportSummaryNotification(
                                'Work Orders',
                                $importedCount,
                                $alreadyExistsCount + $duplicateInBatch,
                                '/admin/work-orders'
                            )
                        );
                    }

                    $this->dispatch('databaseNotificationsSent');
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('[ImportWorkOrders] Error inesperado: ' . $e->getMessage(), [
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
