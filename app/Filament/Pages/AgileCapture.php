<?php

namespace App\Filament\Pages;

use App\Models\DrillHole;
use App\Models\DrillHoleGapValidation;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\SampleSetting;
use App\Models\Sede;
use App\Models\StandardSample;
use App\Models\WorkOrder;
use App\Services\SampleValidationService;
use Filament\Actions\BulkAction;
use Filament\Actions\Action as TableAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use App\Filament\Actions\QcPhotosAction;

class AgileCapture extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Captura Ágil';
    protected static ?string $title = 'Módulo de Captura Ágil';
    protected static string|\UnitEnum|null $navigationGroup = 'Muestreo';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.agile-capture';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS', 'Geologo'])
            || $user->can('View:AgileCapture');
    }

    // Estado Paso 1
    public $searchBarreno = '';
    public ?int $selectedBarrenoId = null;
    public ?DrillHole $barreno = null;
    public bool $isLoadingBarreno = false;

    /**
     * Obtiene la lista de coincidencias en tiempo real mientras el usuario escribe.
     */
    public function getBarrenoSuggestionsProperty(): Collection
    {
        $term = trim((string) $this->searchBarreno);
        if (mb_strlen($term) < 1) {
            return collect();
        }

        return DrillHole::query()
            ->with(['sede:id,name', 'proyecto:id,nombre'])
            ->withCount('drillHoleSamples')
            ->where('nombre_barreno', 'like', "%{$term}%")
            ->orderByRaw("CASE WHEN nombre_barreno = ? THEN 0 WHEN nombre_barreno LIKE ? THEN 1 WHEN nombre_barreno LIKE ? THEN 2 ELSE 3 END", [$term, "{$term}%", "%{$term}%"])
            ->orderBy('nombre_barreno', 'asc')
            ->limit(10)
            ->get();
    }

    /**
     * Selecciona un barreno directamente por su ID (desde el dropdown de sugerencias).
     */
    public function selectBarrenoById(int $id): void
    {
        $this->isLoadingBarreno = true;
        $barreno = DrillHole::find($id);

        if (!$barreno) {
            $this->isLoadingBarreno = false;
            return;
        }

        $this->barreno = $barreno;
        $this->searchBarreno = $barreno->nombre_barreno;
        $this->loadSelectedBarrenoData();
    }

    /**
     * Busca y selecciona el barreno ingresado en el input.
     */
    public function selectBarreno(): void
    {
        $this->isLoadingBarreno = true;
        $term = trim((string) $this->searchBarreno);

        if (!empty($term)) {
            $this->barreno = DrillHole::where('nombre_barreno', $term)->first();

            if (!$this->barreno) {
                // Si no coincide exacto, pero hay una coincidencia única que inicia con el término
                $prefixMatches = DrillHole::where('nombre_barreno', 'like', "{$term}%")->get();
                if ($prefixMatches->count() === 1) {
                    $this->barreno = $prefixMatches->first();
                    $this->searchBarreno = $this->barreno->nombre_barreno;
                } else {
                    Notification::make()
                        ->title('Barreno no encontrado')
                        ->body("No se encontró ningún barreno con el nombre \"{$term}\".")
                        ->danger()
                        ->actions([
                            \Filament\Actions\Action::make('crear')
                                ->label("Crear Barreno \"{$term}\"")
                                ->url(\App\Filament\Resources\DrillHoles\DrillHoleResource::getUrl('create', ['nombre_barreno' => $term]))
                                ->button(),
                        ])
                        ->send();

                    $this->isLoadingBarreno = false;
                    return;
                }
            }

            $this->loadSelectedBarrenoData();
        }

        $this->isLoadingBarreno = false;
    }

    /**
     * Limpia la selección actual del barreno para buscar otro.
     */
    public function clearBarreno(): void
    {
        $this->searchBarreno = '';
        $this->selectedBarrenoId = null;
        $this->barreno = null;
        $this->isLoadingBarreno = false;
        $this->cachedBarrenoMetrics = null;
        $this->flushCachedTableRecords();
        $this->resetPage();
    }

    /**
     * Calcula de manera robusta el siguiente número correlativo de muestra para un barreno.
     * Busca la muestra con el correlativo/secuencia numérica más alto dentro del barreno,
     * incrementa dicho número preservando prefijos y relleno con ceros a la izquierda,
     * y garantiza que no colisione con muestras ya registradas en el proyecto.
     */
    protected function calculateNextSampleNumber(?int $barrenoId, ?int $proyectoId = null): string
    {
        if (!$barrenoId) {
            return '';
        }

        $samples = DrillHoleSample::where('barreno_id', $barrenoId)
            ->whereNotNull('sample_number')
            ->where('sample_number', '!=', '')
            ->get(['id', 'sample_number']);

        if ($samples->isEmpty()) {
            return '';
        }

        // Ordenamiento natural por el sufijo numérico más alto
        $highestSample = $samples->sortByDesc(function ($s) {
            $num = trim((string) $s->sample_number);
            if (preg_match('/(\d+)$/', $num, $matches)) {
                return (int) $matches[1];
            }
            return 0;
        })->first();

        if (!$highestSample) {
            return '';
        }

        $rawNumber = trim((string) $highestSample->sample_number);
        if ($rawNumber === '') {
            return '';
        }

        // Incrementar respetando prefijo y ceros a la izquierda
        if (preg_match('/^(.*?)(\d+)$/', $rawNumber, $matches)) {
            $prefix = $matches[1];
            $digits = $matches[2];
            $padLen = strlen($digits);
            $nextVal = (int) $digits + 1;
            $candidate = $prefix . str_pad((string) $nextVal, $padLen, '0', STR_PAD_LEFT);
        } else {
            $candidate = $rawNumber . '1';
        }

        // Prevenir colisión si el número ya existe en el proyecto
        if ($proyectoId) {
            $existingProjectNumbers = DrillHoleSample::where('proyecto_id', $proyectoId)
                ->pluck('sample_number')
                ->map(fn($n) => trim((string) $n))
                ->flip();

            while (isset($existingProjectNumbers[$candidate])) {
                if (preg_match('/^(.*?)(\d+)$/', $candidate, $matches)) {
                    $prefix = $matches[1];
                    $digits = $matches[2];
                    $padLen = strlen($digits);
                    $nextVal = (int) $digits + 1;
                    $candidate = $prefix . str_pad((string) $nextVal, $padLen, '0', STR_PAD_LEFT);
                } else {
                    $candidate .= '1';
                }
            }
        }

        return $candidate;
    }

    /**
     * Carga las métricas, intervalos y estado de captura del barreno seleccionado.
     */
    protected function loadSelectedBarrenoData(): void
    {
        $this->selectedBarrenoId = $this->barreno->id;

        $lastSample = DrillHoleSample::where('barreno_id', $this->barreno->id)
            ->whereNotNull('to_depth')
            ->orderBy('to_depth', 'desc')
            ->first() ?? DrillHoleSample::where('barreno_id', $this->barreno->id)
            ->orderBy('id', 'desc')
            ->first();

        $lastOriginalSample = DrillHoleSample::where('barreno_id', $this->barreno->id)
            ->where('sample_type', 'O')
            ->whereNotNull('to_depth')
            ->orderBy('to_depth', 'desc')
            ->first();

        $existingIntervals = DrillHoleSample::where('barreno_id', $this->barreno->id)
            ->where('sample_type', 'O')
            ->whereNotNull('from_depth')
            ->whereNotNull('to_depth')
            ->get(['from_depth', 'to_depth'])
            ->toArray();

        $existingSampleNumbers = DrillHoleSample::where('proyecto_id', $this->barreno->proyecto_id)
            ->pluck('sample_number')
            ->toArray();

        $nextSampleNumber = $this->calculateNextSampleNumber($this->barreno->id, $this->barreno->proyecto_id);

        // Emitimos evento para que Alpine.js reaccione e inicie el batch
        $this->dispatch('barreno-selected', [
            'selectedBarrenoId' => $this->barreno->id,
            'nextSampleNumber' => $nextSampleNumber,
            'fromDepth' => $lastOriginalSample ? $lastOriginalSample->to_depth : 0,
            'proyectoId' => $this->barreno->proyecto_id,
            'sampledAt' => $lastSample && $lastSample->sampled_at
                ? $lastSample->sampled_at->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s'),
            'existingIntervals' => $existingIntervals,
            'existingSampleNumbers' => $existingSampleNumbers,
            'maxDepth' => $this->barreno->max_depth,
        ]);

        $this->isLoadingBarreno = false;

        // Invalidar cache de métricas y de la tabla
        $this->cachedBarrenoMetrics = null;
        $this->flushCachedTableRecords();
        $this->resetPage();
    }

    public function getProyectosOptionsProperty()
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['super_admin', 'supervisor'])) {
            return Proyecto::select('id', 'nombre')->get();
        }
        return Proyecto::where('sede_id', $user->sede_id)->select('id', 'nombre')->get();
    }

    public function getEstandaresOptionsProperty()
    {
        return StandardSample::where('status', true)->select('id', 'standard_name')->get();
    }

    public function getMuestrasOriginalesOptionsProperty()
    {
        if (!$this->selectedBarrenoId)
            return collect([]);
        return DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
            ->where('sample_type', 'O')
            ->select('id', 'sample_number')
            ->get();
    }

    /**
     * Devuelve el label de blanco apropiado según la sede del usuario autenticado.
     * Media Luna → BLANK-ML, cualquier otra sede → BLANK
     */
    public function getBlankLabelProperty(): string
    {
        $sede = auth()->user()->sede;
        if ($sede && str_contains(strtolower($sede->name), 'media luna')) {
            return 'BLANK-ML';
        }
        return 'BLANK';
    }

    public function saveBatch($samplesData)
    {
        $errors = [];
        $savedCount = 0;
        $maxDepth = $this->barreno?->max_depth;

        // Intervalos para validar traslape (base de datos y lote actual)
        $existingIntervals = \App\Models\DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
            ->where('sample_type', 'O')
            ->whereNotNull('from_depth')
            ->whereNotNull('to_depth')
            ->get(['from_depth', 'to_depth']);

        foreach ($samplesData as $index => $data) {
            $isControl = ($data['sample_type'] ?? 'O') === 'Control';

            // 1. Validaciones de obligatoriedad (no vacíos)
            if (empty($data['proyecto_id'])) {
                $errors[] = "Fila " . ($index + 1) . ": El Proyecto es requerido.";
                continue;
            }
            if (empty($data['sample_number']) || trim((string)$data['sample_number']) === '') {
                $errors[] = "Fila " . ($index + 1) . ": El Número de Muestra es requerido.";
                continue;
            }
            if (empty($data['sampled_at']) || trim((string)$data['sampled_at']) === '') {
                $errors[] = "Fila " . ($index + 1) . ": La Fecha es requerida.";
                continue;
            }

            if ($isControl) {
                if (empty($data['control_type']) || trim((string)$data['control_type']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": El Tipo de Control es requerido.";
                    continue;
                }
                if ($data['control_type'] === 'Standard' && empty($data['standard_sample_id'])) {
                    $errors[] = "Fila " . ($index + 1) . ": El Estándar de Referencia es requerido.";
                    continue;
                }
                if ($data['control_type'] === 'Duplicate' && empty($data['duplicate_sample_id'])) {
                    $errors[] = "Fila " . ($index + 1) . ": La Muestra Original duplicada es requerida.";
                    continue;
                }
                $minWeight = (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50);
                $maxWeight = (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00);

                if (isset($data['weight']) && trim((string)$data['weight']) !== '') {
                    $wVal = (float) $data['weight'];
                    if ($wVal < $minWeight || $wVal > $maxWeight) {
                        $errors[] = "Fila " . ($index + 1) . ": El Peso ({$wVal} kg) debe estar entre {$minWeight} kg y {$maxWeight} kg.";
                        continue;
                    }
                }
            } else {
                // Original: from, to, sample_length son requeridos (peso es opcional)
                if (!isset($data['from_depth']) || trim((string)$data['from_depth']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": El valor 'Desde (FROM)' es requerido para muestras originales.";
                    continue;
                }
                if (!isset($data['to_depth']) || trim((string)$data['to_depth']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": El valor 'Hasta (TO)' es requerido para muestras originales.";
                    continue;
                }
                if (!isset($data['sample_length']) || trim((string)$data['sample_length']) === '') {
                    $errors[] = "Fila " . ($index + 1) . ": La recuperación (Sample Length) es requerida para muestras originales.";
                    continue;
                }
                if (isset($data['weight']) && trim((string)$data['weight']) !== '') {
                    $wVal = (float) $data['weight'];
                    $minWeight = (float) (\App\Models\SampleSetting::getSettings()->min_sample_weight ?? 0.50);
                    $maxWeight = (float) (\App\Models\SampleSetting::getSettings()->max_sample_weight ?? 15.00);
                    if ($wVal < $minWeight || $wVal > $maxWeight) {
                        $errors[] = "Fila " . ($index + 1) . ": El Peso ({$wVal} kg) debe estar entre {$minWeight} kg y {$maxWeight} kg.";
                        continue;
                    }
                }
            }

            // Validar duplicado en el mismo lote
            $duplicateInBatch = false;
            foreach ($samplesData as $checkIndex => $checkData) {
                if ($checkIndex !== $index && $checkData['sample_number'] === $data['sample_number']) {
                    $duplicateInBatch = true;
                    break;
                }
            }
            if ($duplicateInBatch) {
                $errors[] = "Fila " . ($index + 1) . ": Número de muestra duplicado en el lote.";
                continue;
            }

            // Validar duplicado en el proyecto
            $exists = DrillHoleSample::where('proyecto_id', $data['proyecto_id'])
                ->where('sample_number', $data['sample_number'])
                ->exists();

            if ($exists) {
                $errors[] = "Fila " . ($index + 1) . ": Ya existe la muestra \"{$data['sample_number']}\".";
                continue;
            }

            // Validar duplicado en muestras históricas
            $existsHistorical = \App\Models\HistoricalSample::where('sample_number', $data['sample_number'])
                ->where(function ($q) use ($data) {
                    $q->where('proyecto_id', $data['proyecto_id'])
                      ->orWhereNull('proyecto_id');
                })
                ->exists();

            if ($existsHistorical) {
                $errors[] = "Fila " . ($index + 1) . ": La muestra \"{$data['sample_number']}\" ya existe en el registro histórico.";
                continue;
            }

            $data['barreno_id'] = $this->selectedBarrenoId;
            $data['user_id'] = auth()->id();
            $data['capture_source'] = 'manual';
            $data['qr_token'] = (string) Str::uuid();

            // Limpiar ID generado por Alpine.js
            unset($data['id']);

            // Convertir strings vacíos a null para campos opcionales
            foreach (['standard_sample_id', 'duplicate_sample_id', 'weight', 'sample_length', 'comentarios', 'from_depth', 'to_depth'] as $field) {
                if (isset($data[$field]) && trim((string)$data[$field]) === '') {
                    $data[$field] = null;
                }
            }

            // Si es Control, forzar intervalos a null
            if ($isControl) {
                $data['from_depth'] = null;
                $data['to_depth'] = null;
                $data['length'] = null;
                $data['sample_length'] = null;

                // Limpiar campos dependiendo del tipo de control
                if (($data['control_type'] ?? '') !== 'Standard') {
                    $data['standard_sample_id'] = null;
                }
                if (($data['control_type'] ?? '') !== 'Duplicate') {
                    $data['duplicate_sample_id'] = null;
                }
            } else {
                // Si es Original, forzar controles a null
                $data['control_type'] = null;
                $data['standard_sample_id'] = null;
                $data['duplicate_sample_id'] = null;

                $fd = (float) $data['from_depth'];
                $td = (float) $data['to_depth'];
                $data['length'] = round($td - $fd, 2);

                if ($fd >= $td) {
                    $errors[] = "Fila " . ($index + 1) . ": El valor Desde ({$fd}) debe ser menor que Hasta ({$td}).";
                    continue;
                }

                // Validación de profundidad máxima (max_depth)
                if ($maxDepth !== null && $maxDepth > 0) {
                    if ($fd > $maxDepth) {
                        $errors[] = "Fila " . ($index + 1) . ": El valor Desde ({$fd}m) no puede superar la profundidad máxima del barreno ({$maxDepth}m).";
                        continue;
                    }
                    if ($td > $maxDepth) {
                        $errors[] = "Fila " . ($index + 1) . ": El valor Hasta ({$td}m) no puede superar la profundidad máxima del barreno ({$maxDepth}m).";
                        continue;
                    }
                }

                // Validar Recuperación vs Longitud
                if (isset($data['sample_length']) && (float) $data['sample_length'] > $data['length']) {
                    $errors[] = "Fila " . ($index + 1) . ": Recuperación mayor a la longitud.";
                    continue;
                }

                // Validar Traslape Backend
                $hasOverlap = false;
                foreach ($existingIntervals as $existing) {
                    if (max($fd, (float) $existing->from_depth) < min($td, (float) $existing->to_depth)) {
                        $hasOverlap = true;
                        break;
                    }
                }

                if ($hasOverlap) {
                    $errors[] = "Fila " . ($index + 1) . ": Traslape detectado en intervalos.";
                    continue;
                }

                // Añadir al pool de intervalos si es válido
                $existingIntervals->push((object) ['from_depth' => $fd, 'to_depth' => $td]);
            }

            DrillHoleSample::create($data);
            $savedCount++;
        }


        if (count($errors) > 0) {
            $errorMsg = implode('<br>', $errors);
            \Filament\Notifications\Notification::make()
                ->title('Errores en el lote')
                ->body($errorMsg)
                ->danger()
                ->persistent()
                ->send();
        }

        if ($savedCount > 0) {
            $this->cachedBarrenoMetrics = null;
            $this->flushCachedTableRecords();
            $this->resetPage();

            $qcCountInBatch = collect($samplesData)->filter(function ($d) {
                $type = $d['sample_type'] ?? 'O';
                $cType = $d['control_type'] ?? '';
                return $type === 'Control' && in_array($cType, ['Standard', 'Blank', 'BLANK', 'BLANK-ML', 'BLANK-SN']);
            })->count();

            $notificationBody = "Se han guardado {$savedCount} muestras correctamente.";
            if ($qcCountInBatch > 0) {
                $notificationBody .= " Se incluyeron {$qcCountInBatch} muestra(s) de control (estándar/blanco). Recuerda cargar sus 2 fotografías obligatorias.";
            }

            \Filament\Notifications\Notification::make()
                ->title('Lote guardado')
                ->body($notificationBody)
                ->success()
                ->send();

            $lastSample = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->whereNotNull('to_depth')
                ->orderBy('to_depth', 'desc')
                ->first() ?? DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->orderBy('id', 'desc')
                ->first();

            $lastOriginalSample = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->where('sample_type', 'O')
                ->whereNotNull('to_depth')
                ->orderBy('to_depth', 'desc')
                ->first();

            $existingIntervals = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
                ->where('sample_type', 'O')
                ->whereNotNull('from_depth')
                ->whereNotNull('to_depth')
                ->get(['from_depth', 'to_depth'])
                ->toArray();

            $proyectoId = DrillHole::find($this->selectedBarrenoId)?->proyecto_id;
            $existingSampleNumbers = DrillHoleSample::where('proyecto_id', $proyectoId)
                ->pluck('sample_number')
                ->toArray();

            $nextSampleNumber = $this->calculateNextSampleNumber($this->selectedBarrenoId, $proyectoId);

            // Reiniciar el componente alpine
            $this->dispatch('batch-saved', [
                'nextSampleNumber' => $nextSampleNumber,
                'fromDepth' => $lastOriginalSample ? $lastOriginalSample->to_depth : 0,
                'existingIntervals' => $existingIntervals,
                'existingSampleNumbers' => $existingSampleNumbers,
            ]);
        }
    }

    /**
     * Exporta a Excel (.xlsx) las muestras seleccionadas en la tabla, respetando el orden activo.
     */
    public function exportSamplesToExcel(Collection $records): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $barrenoName = $this->barreno?->nombre_barreno ?? 'barreno';
        $fileName = 'captura-agil-' . Str::slug($barrenoName) . '-' . now()->format('Ymd-His') . '.xlsx';

        $query = DrillHoleSample::query()
            ->whereIn('id', $records->pluck('id'))
            ->orderBy('sample_number', 'asc')
            ->with(['standardSample', 'workOrder', 'barreno'])
            ->withCount('qcPhotos');

        return response()->streamDownload(function () use ($query) {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile('php://output');

            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                'Barreno', 'Sample Number', 'From', 'To', 'Drilled Length', 'Sample Length',
                'Sample Type', 'Control Type', 'Fotos QC', 'Core Size', 'Weight',
                'Fecha Muestreo', 'WO', 'Comentarios',
            ]));

            $query->chunk(500, function ($samples) use ($writer) {
                foreach ($samples as $s) {
                    $controlType = $s->control_type;
                    if (in_array($controlType, ['Standard', 'Estándar'], true) && $s->standardSample) {
                        $controlType = 'STD-' . $s->standardSample->standard_name;
                    }

                    $photos = $s->requiresQcPhotos()
                        ? ($s->qc_photos_count . '/' . DrillHoleSample::QC_PHOTOS_REQUIRED)
                        : '—';

                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                        $s->barreno?->nombre_barreno,
                        $s->sample_number,
                        $s->from_depth !== null ? (float) $s->from_depth : null,
                        $s->to_depth !== null ? (float) $s->to_depth : null,
                        $s->length !== null ? (float) $s->length : null,
                        $s->sample_length !== null ? (float) $s->sample_length : null,
                        $s->sample_type,
                        $controlType,
                        $photos,
                        $s->core_size,
                        $s->weight !== null ? (float) $s->weight : null,
                        $s->sampled_at?->format('d/m/Y H:i'),
                        $s->workOrder?->work_order_code,
                        $s->comentarios,
                    ]));
                }
            });

            $writer->close();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() => DrillHoleSample::query()
                    ->with(['standardSample', 'qcPhotos', 'workOrder', 'barreno'])
                    ->withCount('qcPhotos')
                    ->where('barreno_id', $this->selectedBarrenoId ?? -1)
                    ->orderBy('sample_number', 'asc')
            )
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 75, 100])
            ->columns([
                TextColumn::make('sample_number')
                    ->label('Sample Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('from_depth')
                    ->label('From')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('to_depth')
                    ->label('To')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('length')
                    ->label('Length')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sample_length')
                    ->label('Sample Length')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sample_type')
                    ->label('Sample Type')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('control_type')
                    ->label('Control Type')
                    ->badge()
                    ->color(fn($state) => ($state && str_starts_with($state, 'STD-')) ? 'info' : 'gray')
                    ->formatStateUsing(function ($state, $record) {
                        if (($state === 'Standard' || $state === 'Estándar') && $record->standardSample) {
                            return 'STD-' . $record->standardSample->standard_name;
                        }
                        return $state;
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('qc_photos_status')
                    ->label('Fotos QC')
                    ->badge()
                    ->state(function (DrillHoleSample $record): string {
                        if (!$record->requiresQcPhotos()) {
                            return '—';
                        }
                        $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                        return "{$count}/" . DrillHoleSample::QC_PHOTOS_REQUIRED;
                    })
                    ->color(function (DrillHoleSample $record): string {
                        if (!$record->requiresQcPhotos()) {
                            return 'gray';
                        }
                        $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                        return $count >= DrillHoleSample::QC_PHOTOS_REQUIRED ? 'success' : 'danger';
                    })
                    ->tooltip(function (DrillHoleSample $record): ?string {
                        if (!$record->requiresQcPhotos()) return null;
                        $count = $record->qc_photos_count ?? $record->qcPhotos()->count();
                        return $count >= DrillHoleSample::QC_PHOTOS_REQUIRED ? 'Fotos completas (2/2)' : 'Faltan fotos obligatorias de control';
                    })
                    ->sortable(false)
                    ->toggleable(),
                TextColumn::make('core_size')
                    ->label('Core Size')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'PQ' => 'info',
                        'HQ' => 'success',
                        'NQ' => 'warning',
                        'BQ' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextInputColumn::make('weight')
                    ->label('Wght')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(fn (DrillHoleSample $record): bool => !auth()->user()?->hasRole(['super_admin', 'Admin CoreFlow']) && ($record->is_archived || !empty($record->workOrder?->dispatch_date)))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sampled_at')
                    ->label('Fecha Muestreo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('workOrder.work_order_code')
                    ->label('WO')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('comentarios')
                    ->label('Comentarios')
                    ->limit(40)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                QcPhotosAction::make(),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (DrillHoleSample $record) => "Editar Muestra #{$record->sample_number}")
                    ->modalDescription(fn (DrillHoleSample $record) => "Barreno: " . ($record->barreno?->nombre_barreno ?? 'N/A') . " | Proyecto: " . ($record->proyecto?->nombre ?? 'N/A'))
                    ->modalSubmitActionLabel('Guardar Cambios')
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('3xl')
                    ->slideOver()
                    ->disabled(fn (DrillHoleSample $record): bool => $record->isSent())
                    ->tooltip(fn (DrillHoleSample $record): ?string => $record->isSent()
                        ? 'Esta muestra está bloqueada: pertenece a una WO ya enviada al laboratorio.'
                        : null
                    )
                    ->form([
                        Placeholder::make('navigation_header')
                            ->hiddenLabel()
                            ->content(fn (?DrillHoleSample $record) => $record ? static::renderNavigationHeaderHtml($record) : null),

                        Placeholder::make('validation_live_status')
                            ->hiddenLabel()
                            ->content(function (Get $get, ?DrillHoleSample $record) {
                                if (!$record) return null;
                                $eval = static::evaluateSampleInterval(
                                    $record,
                                    $get('from_depth'),
                                    $get('to_depth'),
                                    $get('sample_length'),
                                    $get('weight')
                                );
                                return static::renderValidationLiveStatusHtml($eval);
                            }),

                        Section::make('Intervalo de Perforación')
                            ->description('Definición de tramos y recuperación real')
                            ->icon('heroicon-o-arrows-up-down')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('from_depth')
                                        ->label('Desde (FROM)')
                                        ->numeric()
                                        ->step(0.01)
                                        ->required(fn (?DrillHoleSample $record) => $record?->sample_type !== 'Control')
                                        ->disabled(fn (?DrillHoleSample $record) => $record?->sample_type === 'Control')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            $from = is_numeric($state) ? (float) $state : null;
                                            $to = is_numeric($get('to_depth')) ? (float) $get('to_depth') : null;
                                            if ($from !== null && $to !== null && $to > $from) {
                                                $set('length', round($to - $from, 2));
                                            }
                                        })
                                        ->rules([
                                            fn (Get $get, ?DrillHoleSample $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                                if (!$record || $record->sample_type === 'Control' || !is_numeric($value)) return;
                                                $from = (float) $value;
                                                $to = is_numeric($get('to_depth')) ? (float) $get('to_depth') : null;
                                                if ($from < 0) {
                                                    $fail("La profundidad 'Desde' ({$from} m) no puede ser negativa.");
                                                    return;
                                                }
                                                if ($to !== null && $from >= $to) {
                                                    $fail("La profundidad 'Desde' ({$from} m) debe ser menor que 'Hasta' ({$to} m).");
                                                    return;
                                                }
                                                if ($to !== null) {
                                                    $overlap = DrillHoleSample::where('barreno_id', $record->barreno_id)
                                                        ->where('id', '!=', $record->id)
                                                        ->whereNotNull('from_depth')
                                                        ->whereNotNull('to_depth')
                                                        ->where('from_depth', '<', $to - 0.001)
                                                        ->where('to_depth', '>', $from + 0.001)
                                                        ->first();
                                                    if ($overlap) {
                                                        $fail("Traslape detectado con muestra #{$overlap->sample_number} ({$overlap->from_depth}m - {$overlap->to_depth}m).");
                                                    }
                                                }
                                            },
                                        ]),

                                    TextInput::make('to_depth')
                                        ->label('Hasta (TO)')
                                        ->numeric()
                                        ->step(0.01)
                                        ->required(fn (?DrillHoleSample $record) => $record?->sample_type !== 'Control')
                                        ->disabled(fn (?DrillHoleSample $record) => $record?->sample_type === 'Control')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            $to = is_numeric($state) ? (float) $state : null;
                                            $from = is_numeric($get('from_depth')) ? (float) $get('from_depth') : null;
                                            if ($from !== null && $to !== null && $to > $from) {
                                                $set('length', round($to - $from, 2));
                                            }
                                        })
                                        ->rules([
                                            fn (Get $get, ?DrillHoleSample $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                                if (!$record || $record->sample_type === 'Control' || !is_numeric($value)) return;
                                                $to = (float) $value;
                                                $from = is_numeric($get('from_depth')) ? (float) $get('from_depth') : null;
                                                if ($from !== null && $from >= $to) {
                                                    $fail("La profundidad 'Hasta' ({$to} m) debe ser mayor que 'Desde' ({$from} m).");
                                                    return;
                                                }
                                                $barreno = $record->barreno ?: DrillHole::find($record->barreno_id);
                                                if ($barreno?->max_depth !== null && (float)$barreno->max_depth > 0 && $to > (float)$barreno->max_depth + 0.001) {
                                                    $fail("La profundidad 'Hasta' ({$to} m) excede la profundidad máxima del barreno ({$barreno->max_depth} m).");
                                                    return;
                                                }
                                                if ($from !== null) {
                                                    $overlap = DrillHoleSample::where('barreno_id', $record->barreno_id)
                                                        ->where('id', '!=', $record->id)
                                                        ->whereNotNull('from_depth')
                                                        ->whereNotNull('to_depth')
                                                        ->where('from_depth', '<', $to - 0.001)
                                                        ->where('to_depth', '>', $from + 0.001)
                                                        ->first();
                                                    if ($overlap) {
                                                        $fail("Traslape detectado con muestra #{$overlap->sample_number} ({$overlap->from_depth}m - {$overlap->to_depth}m).");
                                                    }
                                                }
                                            },
                                        ]),

                                    TextInput::make('length')
                                        ->label('Longitud (m)')
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric()
                                        ->step(0.01)
                                        ->formatStateUsing(function ($state, ?DrillHoleSample $record, Get $get) {
                                            if ($state !== null && $state !== '') return $state;
                                            $from = (float) $get('from_depth');
                                            $to = (float) $get('to_depth');
                                            return ($to > $from) ? round($to - $from, 2) : ($record?->length ?? null);
                                        })
                                        ->helperText('Diferencia automática TO - FROM'),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('sample_length')
                                        ->label('Recuperación (Sample Length) [m]')
                                        ->numeric()
                                        ->step(0.01)
                                        ->disabled(fn (?DrillHoleSample $record) => $record?->sample_type === 'Control')
                                        ->live(onBlur: true)
                                        ->helperText('Testigo físico recuperado en metros')
                                        ->rules([
                                            fn (Get $get, ?DrillHoleSample $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                                if (!$record || $record->sample_type === 'Control' || $value === null || $value === '') return;
                                                $recup = (float) $value;
                                                $from = is_numeric($get('from_depth')) ? (float) $get('from_depth') : null;
                                                $to = is_numeric($get('to_depth')) ? (float) $get('to_depth') : null;
                                                if ($from !== null && $to !== null && $to > $from) {
                                                    $len = round($to - $from, 2);
                                                    if ($recup > $len + 0.001) {
                                                        $fail("La recuperación ({$recup} m) no puede ser mayor que la longitud ({$len} m).");
                                                    }
                                                }
                                            },
                                        ]),

                                    Select::make('core_size')
                                        ->label('Diámetro (Core Size)')
                                        ->options([
                                            'PQ' => 'PQ (85.0 mm)',
                                            'HQ' => 'HQ (63.5 mm)',
                                            'NQ' => 'NQ (47.6 mm)',
                                            'BQ' => 'BQ (36.4 mm)',
                                        ])
                                        ->placeholder('Seleccione diámetro...'),
                                ]),
                            ]),

                        Section::make('Propiedades Físicas y QA/QC')
                            ->description('Parámetros de masa y controles')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('weight')
                                        ->label('Peso (kg)')
                                        ->numeric()
                                        ->step(0.01)
                                        ->live(onBlur: true)
                                        ->helperText(function () {
                                            $min = SampleSetting::getSettings()->min_sample_weight ?? 0.50;
                                            $max = SampleSetting::getSettings()->max_sample_weight ?? 15.00;
                                            return "Rango esperado: {$min} kg a {$max} kg";
                                        }),

                                    Select::make('control_type')
                                        ->label('Tipo de Control')
                                        ->options([
                                            'Blank-ML'  => 'Blank-ML',
                                            'Standard'  => 'Standard',
                                            'Duplicate' => 'Duplicate',
                                        ])
                                        ->visible(fn (?DrillHoleSample $record) => $record?->sample_type === 'Control'),
                                ]),
                            ]),

                        Section::make('Observaciones')
                            ->description('Notas geológicas u operativas')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Textarea::make('comentarios')
                                    ->label('Comentarios')
                                    ->placeholder('Observaciones relevantes sobre la muestra o el tramo...')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->extraModalFooterActions(function (DrillHoleSample $record, Page $livewire): array {
                        $allIds = static::getOrderedBarrenoSampleIds($record->barreno_id);
                        $currentIndex = array_search($record->id, $allIds);
                        $total = count($allIds);
                        $prevId = ($currentIndex !== false && $currentIndex > 0) ? $allIds[$currentIndex - 1] : null;
                        $nextId = ($currentIndex !== false && $currentIndex < $total - 1) ? $allIds[$currentIndex + 1] : null;

                        $actions = [];
                        if ($prevId) {
                            $actions[] = TableAction::make('prev_sample_footer')
                                ->label('← Anterior')
                                ->color('gray')
                                ->action(fn () => $livewire->navigateEditSample($prevId));
                        }
                        if ($nextId) {
                            $actions[] = TableAction::make('next_sample_footer')
                                ->label('Siguiente →')
                                ->color('gray')
                                ->action(fn () => $livewire->navigateEditSample($nextId));
                        }
                        return $actions;
                    })
                    ->before(function (EditAction $action, DrillHoleSample $record, array $data) {
                        if ($record->sample_type !== 'Control') {
                            $from = isset($data['from_depth']) && is_numeric($data['from_depth']) ? (float)$data['from_depth'] : null;
                            $to = isset($data['to_depth']) && is_numeric($data['to_depth']) ? (float)$data['to_depth'] : null;
                            $recup = isset($data['sample_length']) && is_numeric($data['sample_length']) ? (float)$data['sample_length'] : null;
                            $weight = isset($data['weight']) && is_numeric($data['weight']) ? (float)$data['weight'] : null;

                            $eval = static::evaluateSampleInterval($record, $from, $to, $recup, $weight);

                            if (!empty($eval['errors'])) {
                                $msg = implode(' ', $eval['errors']);
                                Notification::make()
                                    ->title('No se puede guardar la muestra')
                                    ->body($msg)
                                    ->danger()
                                    ->send();

                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'from_depth' => $eval['errors'][0],
                                ]);
                            }
                        }
                    })
                    ->using(function (DrillHoleSample $record, array $data): DrillHoleSample {
                        if ($record->sample_type !== 'Control' && isset($data['from_depth'], $data['to_depth']) && is_numeric($data['from_depth']) && is_numeric($data['to_depth'])) {
                            $data['length'] = round((float)$data['to_depth'] - (float)$data['from_depth'], 2);
                        }
                        $record->update($data);
                        return $record;
                    })
                    ->after(function (DrillHoleSample $record, Page $livewire) {
                        app(SampleValidationService::class)->validateDraftsForBarreno($record->barreno_id);
                        if (method_exists($livewire, 'flushCachedTableRecords')) {
                            $livewire->flushCachedTableRecords();
                        }
                        Notification::make()
                            ->title("Muestra {$record->sample_number} actualizada")
                            ->body("Datos guardados y validaciones de perforación sincronizadas con éxito.")
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->disabled(fn (DrillHoleSample $record): bool => $record->isSent())
                    ->tooltip(fn (DrillHoleSample $record): ?string => $record->isSent()
                        ? 'No se puede eliminar: esta muestra pertenece a una WO ya enviada al laboratorio.'
                        : null
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar Muestra')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta muestra? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar'),
            ])
            ->filters([
                SelectFilter::make('tipo_muestra')
                    ->label('Tipo de Muestra')
                    ->placeholder('Todos los tipos')
                    ->options([
                        'O'         => 'Originales (O)',
                        'standard'  => 'Estándares (STD)',
                        'blank'     => 'Blancos (BLANK)',
                        'duplicate' => 'Duplicados (DUP)',
                        'control'   => 'Todos los Controles (QA/QC)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (!$value) {
                            return $query;
                        }

                        return match ($value) {
                            'O' => $query->where(function (Builder $q) {
                                $q->where('sample_type', 'O')
                                  ->orWhere('sample_type', 'Original')
                                  ->orWhere(fn($sub) => $sub->whereNull('sample_type')->whereNull('control_type'));
                            }),
                            'standard' => $query->where(function (Builder $q) {
                                $q->where(function ($sub) {
                                    $sub->where('control_type', 'Standard')
                                        ->orWhere('control_type', 'Estándar')
                                        ->orWhere('control_type', 'like', 'STD-%');
                                });
                            }),
                            'blank' => $query->where(function (Builder $q) {
                                $q->where(function ($sub) {
                                    $sub->where('control_type', 'like', 'Blank%')
                                        ->orWhere('control_type', 'like', 'BLANK%')
                                        ->orWhere('control_type', 'Blanco');
                                });
                            }),
                            'duplicate' => $query->where(function (Builder $q) {
                                $q->whereNotNull('duplicate_sample_id')
                                  ->orWhere('control_type', 'like', '%Dup%')
                                  ->orWhere('control_type', 'like', '%DUP%')
                                  ->orWhere('control_type', 'Duplicado');
                            }),
                            'control' => $query->where('sample_type', 'Control'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('work_order_status')
                    ->label('Estado Work Order')
                    ->placeholder('Todas las muestras')
                    ->options([
                        'with_wo'    => 'Con Work Order asignada',
                        'without_wo' => 'Sin Work Order (Pendientes)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'with_wo'    => $query->whereNotNull('work_order_id'),
                            'without_wo' => $query->whereNull('work_order_id'),
                            default      => $query,
                        };
                    }),

                SelectFilter::make('work_order_id')
                    ->label('Work Order')
                    ->placeholder('Cualquier Work Order')
                    ->relationship('workOrder', 'work_order_code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('core_size')
                    ->label('Core Size')
                    ->placeholder('Todos los tamaños')
                    ->options([
                        'PQ'          => 'PQ (85.0 mm)',
                        'HQ'          => 'HQ (63.5 mm)',
                        'HQ3'         => 'HQ3 (61.1 mm)',
                        'NQ'          => 'NQ (47.6 mm)',
                        'NQ2'         => 'NQ2 (50.5 mm)',
                        'BQ'          => 'BQ (36.4 mm)',
                        'N/A'         => 'N/A (No Aplica)',
                        'sin_asignar' => 'Sin Core Size (Pendientes)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (!$value) {
                            return $query;
                        }

                        if ($value === 'sin_asignar') {
                            return $query->where(function (Builder $q) {
                                $q->whereNull('core_size')->orWhere('core_size', '');
                            });
                        }

                        return $query->where('core_size', $value);
                    }),
            ])
            ->headerActions([
                TableAction::make('qcPhotosEvidence')
                    ->label(function () {
                        $pending = $this->pendingQcPhotosCount;
                        return $pending > 0 ? "Fotos QC ({$pending} pendientes)" : 'Fotos QC';
                    })
                    ->icon('heroicon-o-camera')
                    ->color(fn () => $this->pendingQcPhotosCount > 0 ? 'warning' : 'gray')
                    ->url(fn () => \App\Filament\Pages\QcPhotoEvidence::getUrl(['barrenoId' => $this->selectedBarrenoId, 'filter' => 'pending']))
                    ->visible(fn () => (bool) $this->selectedBarrenoId),
                TableAction::make('assignCoreSizeHeader')
                    ->label('Asignar Core Size')
                    ->icon('heroicon-o-circle-stack')
                    ->color('info')
                    ->form(function () {
                        return [
                            Select::make('scope')
                                ->label('Aplicar a')
                                ->options([
                                    'all'    => 'Todas las muestras guardadas del barreno',
                                    'range'  => 'Rango de muestras (Muestra Inicio X a Muestra Fin Y)',
                                    'single' => 'Muestra única',
                                ])
                                ->default('all')
                                ->live(),

                            TextInput::make('sample_start')
                                ->label('Muestra Inicio (X)')
                                ->placeholder('Ej: ML157100')
                                ->required(fn(Get $get) => in_array($get('scope'), ['range', 'single']))
                                ->hidden(fn(Get $get) => $get('scope') === 'all'),

                            TextInput::make('sample_end')
                                ->label('Muestra Fin (Y)')
                                ->placeholder('Ej: ML157110')
                                ->required(fn(Get $get) => $get('scope') === 'range')
                                ->hidden(fn(Get $get) => $get('scope') !== 'range'),

                            Select::make('core_size')
                                ->label('Tamaño de Núcleo (Core Size)')
                                ->options([
                                    'BQ'  => 'BQ  (36.5 mm)',
                                    'NQ'  => 'NQ  (47.6 mm)',
                                    'NQ2' => 'NQ2 (50.5 mm)',
                                    'HQ'  => 'HQ  (63.5 mm)',
                                    'HQ3' => 'HQ3 (61.1 mm)',
                                    'PQ'  => 'PQ  (83.0 mm)',
                                    'N/A' => 'N/A (No Aplica)',
                                ])
                                ->required()
                                ->native(false),
                        ];
                    })
                    ->modalHeading('Asignar Core Size a Muestras del Barreno')
                    ->action(function (array $data) {
                        $barrenoId = $this->selectedBarrenoId;
                        if (!$barrenoId) return;

                        $query = DrillHoleSample::where('barreno_id', $barrenoId)
                            ->where('is_archived', false)
                            ->whereDoesntHave('workOrder', fn($q) => $q->whereHas('drillHoleSamples', fn($s) => $s->where('is_archived', true)));
                        $scope = $data['scope'] ?? 'all';
                        $coreSize = $data['core_size'];
                        $updatedCount = 0;

                        if ($scope === 'all') {
                            $updatedCount = (clone $query)->update(['core_size' => $coreSize]);
                        } elseif ($scope === 'single') {
                            $start = trim((string) ($data['sample_start'] ?? ''));
                            $updatedCount = (clone $query)
                                ->where('sample_number', $start)
                                ->update(['core_size' => $coreSize]);
                        } elseif ($scope === 'range') {
                            $start = trim((string) ($data['sample_start'] ?? ''));
                            $end   = trim((string) ($data['sample_end'] ?? ''));

                            $allSamples = (clone $query)->get();
                            $matchedIds = $this->getSampleIdsInRange($allSamples, $start, $end);
                            if (!empty($matchedIds)) {
                                $updatedCount = (clone $query)->whereIn('id', $matchedIds)->update(['core_size' => $coreSize]);
                            }
                        }

                        Notification::make()
                            ->title('Core Size asignado')
                            ->body("Core Size {$coreSize} asignado a {$updatedCount} muestra(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('exportExcel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $this->exportSamplesToExcel($records)),
                BulkAction::make('assignCoreSize')
                    ->label('Asignar Core Size')
                    ->icon('heroicon-o-circle-stack')
                    ->color('info')
                    ->form(function (Collection $records) {
                        $sorted = $records->sortBy(function ($s) {
                            if (preg_match('/(\d+)/', (string) $s->sample_number, $m)) {
                                return (int) $m[1];
                            }
                            return $s->sample_number;
                        })->values();

                        $firstNum = $sorted->first()?->sample_number ?? '';
                        $lastNum  = $sorted->last()?->sample_number ?? '';

                        return [
                            Select::make('scope')
                                ->label('Aplicar a')
                                ->options([
                                    'range'  => 'Rango seleccionado (Muestra Inicio a Fin)',
                                    'single' => 'Muestra única',
                                    'all'    => 'Todas las muestras del barreno',
                                ])
                                ->default($sorted->count() > 1 ? 'range' : 'single')
                                ->live(),

                            TextInput::make('sample_start')
                                ->label('Muestra Inicio (X)')
                                ->default($firstNum)
                                ->required(fn(Get $get) => in_array($get('scope'), ['range', 'single']))
                                ->hidden(fn(Get $get) => $get('scope') === 'all'),

                            TextInput::make('sample_end')
                                ->label('Muestra Fin (Y)')
                                ->default($lastNum)
                                ->required(fn(Get $get) => $get('scope') === 'range')
                                ->hidden(fn(Get $get) => $get('scope') !== 'range'),

                            Select::make('core_size')
                                ->label('Tamaño de Núcleo (Core Size)')
                                ->options([
                                    'BQ'  => 'BQ  (36.5 mm)',
                                    'NQ'  => 'NQ  (47.6 mm)',
                                    'NQ2' => 'NQ2 (50.5 mm)',
                                    'HQ'  => 'HQ  (63.5 mm)',
                                    'HQ3' => 'HQ3 (61.1 mm)',
                                    'PQ'  => 'PQ  (83.0 mm)',
                                    'N/A' => 'N/A (No Aplica)',
                                ])
                                ->required()
                                ->native(false),
                        ];
                    })
                    ->modalHeading('Asignar Core Size masivo')
                    ->modalDescription('Se asignará el Core Size seleccionado a las muestras del barreno.')
                    ->action(function (Collection $records, array $data) {
                        $scope = $data['scope'] ?? 'range';
                        $coreSize = $data['core_size'];
                        $barrenoId = $this->selectedBarrenoId;

                        $query = DrillHoleSample::where('barreno_id', $barrenoId)
                            ->where('is_archived', false)
                            ->whereDoesntHave('workOrder', fn($q) => $q->whereHas('drillHoleSamples', fn($s) => $s->where('is_archived', true)));

                        $updatedCount = 0;
                        if ($scope === 'all') {
                            $updatedCount = (clone $query)->update(['core_size' => $coreSize]);
                        } elseif ($scope === 'single') {
                            $start = trim((string) ($data['sample_start'] ?? ''));
                            $updatedCount = (clone $query)
                                ->where('sample_number', $start)
                                ->update(['core_size' => $coreSize]);
                        } else {
                            $targetIds = $records->pluck('id')->toArray();
                            if (!empty($targetIds)) {
                                $updatedCount = (clone $query)
                                    ->whereIn('id', $targetIds)
                                    ->update(['core_size' => $coreSize]);
                            }
                        }

                        Notification::make()
                            ->title('Core Size asignado')
                            ->body("Core Size {$coreSize} asignado a {$updatedCount} muestra(s) del barreno.")
                            ->success()
                            ->send();
                    }),

                BulkAction::make('assignWorkOrder')
                    ->label('Asignar Work Order')
                    ->icon('heroicon-o-briefcase')
                    ->form(function () {
                        // Obtener sede del barreno actual de forma directa y optimizada
                        $sedeId = $this->barreno?->sede_id;
                        if (!$sedeId && $this->selectedBarrenoId) {
                            $sedeId = DrillHole::where('id', $this->selectedBarrenoId)->value('sede_id')
                                ?? DrillHole::where('id', $this->selectedBarrenoId)->with('proyecto')->first()?->proyecto?->sede_id;
                        }

                        return [
                            Select::make('work_order_id')
                                ->label('Work Order')
                                ->options(function () use ($sedeId) {
                                    $query = WorkOrder::query()
                                        ->select(['id', 'work_order_code', 'samples_quantity']);

                                    if ($sedeId) {
                                        $query->where('sede_id', $sedeId);
                                    }

                                    // Descartar de inmediato WOs ya enviadas al laboratorio (filtro indexado rápido)
                                    $query->where(function ($q) {
                                        $q->where('sent_to_lab', false)->orWhereNull('sent_to_lab');
                                    });

                                    // Solo WOs que NO tienen muestras archivadas
                                    $query->whereDoesntHave('drillHoleSamples', fn($q) => $q->where('is_archived', true));

                                    return $query->orderByDesc('id')
                                        ->get()
                                        ->mapWithKeys(
                                            fn($wo) =>
                                            [$wo->id => "{$wo->work_order_code} (muestras: {$wo->samples_quantity})"]
                                        );
                                })
                                ->searchable()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('work_order_code')
                                        ->label('Código de nueva Work Order')
                                        ->required()
                                        ->unique('work_orders', 'work_order_code')
                                        ->placeholder('Ej: WO-2026-001'),
                                ])
                                ->createOptionUsing(function (array $data) use ($sedeId) {
                                    $wo = WorkOrder::create([
                                        'work_order_code' => trim($data['work_order_code']),
                                        'sede_id' => $sedeId,
                                        'samples_quantity' => 0,
                                    ]);
                                    return $wo->id;
                                }),
                        ];
                    })
                    ->action(function (Collection $records, array $data) {
                        // Verificar que ninguna muestra seleccionada pertenezca a una WO ya enviada
                        $hasLockedSamples = $records->contains(function (DrillHoleSample $s) {
                            return $s->is_archived || ($s->workOrder?->isSent() ?? false);
                        });

                        if ($hasLockedSamples) {
                            \Filament\Notifications\Notification::make()
                                ->title('Operación no permitida')
                                ->body('Una o más muestras seleccionadas ya pertenecen a una Work Order enviada al laboratorio y no pueden reasignarse.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $barrenoId = $this->selectedBarrenoId;
                        $workOrder = WorkOrder::find($data['work_order_id']);

                        if (!$workOrder) {
                            \Filament\Notifications\Notification::make()
                                ->title('Work Order no encontrada')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Verificar que la WO destino no esté enviada al laboratorio
                        if ($workOrder->isSent()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Work Order bloqueada')
                                ->body("La Work Order {$workOrder->work_order_code} ya fue enviada al laboratorio y no puede recibir más muestras.")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Si tiene muestras, verificar que todas sean del mismo barreno
                        if ($workOrder->samples_quantity > 0) {
                            $allSameBarreno = DrillHoleSample::where('work_order_id', $workOrder->id)
                                ->where('barreno_id', '!=', $barrenoId)
                                ->doesntExist();

                            if (!$allSameBarreno) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Work Order no disponible')
                                    ->body("Esta Work Order ya tiene muestras de otros barrenos.")
                                    ->warning()
                                    ->send();
                                return;
                            }
                        }

                        // Validar que todas las muestras seleccionadas tengan Core Size capturado
                        $samplesWithoutCoreSize = $records->filter(function ($sample) {
                            $cs = $sample->core_size ?: $sample->barreno?->core_size;
                            return empty($cs);
                        });

                        if ($samplesWithoutCoreSize->isNotEmpty()) {
                            $count = $samplesWithoutCoreSize->count();
                            $sampleNumbers = $samplesWithoutCoreSize->pluck('sample_number')->take(5)->implode(', ');
                            \Filament\Notifications\Notification::make()
                                ->title('Core Size requerido')
                                ->body("No se puede asignar la Work Order porque {$count} muestra(s) no tienen Core Size asignado ({$sampleNumbers}...). Por favor asigna el Core Size antes de continuar.")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Guardar las WOs anteriores para recalcular
                        $previousWoIds = $records->pluck('work_order_id')
                            ->filter()
                            ->unique()
                            ->values();

                        // 1 solo query batch para asignar la nueva WO a todas las muestras seleccionadas
                        $targetIds = $records->pluck('id')->all();
                        DrillHoleSample::whereIn('id', $targetIds)
                            ->update(['work_order_id' => $workOrder->id]);

                        // Recalcular conteo de WOs afectadas (anteriores + nueva) con 1 query por WO afectada
                        $affectedWoIds = $previousWoIds->push($workOrder->id)->unique()->filter()->values();
                        foreach ($affectedWoIds as $woIdToUpdate) {
                            $this->recalcularWO($woIdToUpdate);
                        }

                        $this->cachedBarrenoMetrics = null;
                        $this->flushCachedTableRecords();
                        $this->resetPage();

                        \Filament\Notifications\Notification::make()
                            ->title('Work Order asignada')
                            ->body("La orden {$workOrder->work_order_code} fue asignada a {$records->count()} muestras.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Recalcula el samples_quantity de una Work Order.
     */
    private function recalcularWO(int $woId): void
    {
        $count = DrillHoleSample::where('work_order_id', $woId)->count();
        WorkOrder::where('id', $woId)->update(['samples_quantity' => $count]);
    }

    protected function generateZpl($records)
    {
        $zplCodes = [];
        foreach ($records as $record) {
            $woCode = $record->workOrder ? $record->workOrder->work_order_code : 'N/A';
            $proyecto = $record->proyecto ? $record->proyecto->nombre : 'N/A';
            $zplCodes[] = "^XA\n^FO50,50^A0N,50,50^FDProyecto: {$proyecto}^FS\n^FO50,120^A0N,50,50^FDWO: {$woCode}^FS\n^FO50,190^A0N,50,50^FDMuestra: {$record->sample_number}^FS\n^FO50,260^BQN,2,5^FDQA,{$record->qr_token}^FS\n^XZ";
        }

        $allZpl = implode("\n", $zplCodes);

        return response()->streamDownload(function () use ($allZpl) {
            echo $allZpl;
        }, 'etiquetas_' . time() . '.zpl');
    }

    protected ?array $cachedBarrenoMetrics = null;

    /**
     * Consolidar consultas de métricas del barreno en una sola consulta SQL optimizada.
     */
    protected function getBarrenoMetrics(): array
    {
        if ($this->cachedBarrenoMetrics !== null) {
            return $this->cachedBarrenoMetrics;
        }

        if (!$this->selectedBarrenoId) {
            return $this->cachedBarrenoMetrics = [
                'total' => 0,
                'estandares' => 0,
                'blancos' => 0,
                'duplicados' => 0,
                'pending_qc' => 0,
            ];
        }

        $row = DrillHoleSample::where('barreno_id', $this->selectedBarrenoId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN control_type = "Standard" THEN 1 ELSE 0 END) as estandares,
                SUM(CASE WHEN control_type LIKE "BLANK%" THEN 1 ELSE 0 END) as blancos,
                SUM(CASE WHEN control_type = "Duplicate" THEN 1 ELSE 0 END) as duplicados,
                SUM(CASE WHEN UPPER(TRIM(sample_type)) = "CONTROL"
                         AND duplicate_sample_id IS NULL
                         AND (control_type IS NULL OR UPPER(control_type) NOT LIKE "%DUP%")
                         AND (SELECT COUNT(*) FROM qc_sample_photos WHERE qc_sample_photos.drill_hole_sample_id = drill_hole_samples.id) < 2
                    THEN 1 ELSE 0 END) as pending_qc
            ')
            ->first();

        return $this->cachedBarrenoMetrics = [
            'total' => (int) ($row?->total ?? 0),
            'estandares' => (int) ($row?->estandares ?? 0),
            'blancos' => (int) ($row?->blancos ?? 0),
            'duplicados' => (int) ($row?->duplicados ?? 0),
            'pending_qc' => (int) ($row?->pending_qc ?? 0),
        ];
    }

    // Calculators for Widgets
    public function getTotalMuestrasProperty(): int
    {
        return $this->getBarrenoMetrics()['total'];
    }

    public function getTotalEstandaresProperty(): int
    {
        return $this->getBarrenoMetrics()['estandares'];
    }

    public function getTotalBlancosProperty(): int
    {
        return $this->getBarrenoMetrics()['blancos'];
    }

    public function getTotalDuplicadosProperty(): int
    {
        return $this->getBarrenoMetrics()['duplicados'];
    }

    protected function getSampleIdsInRange($samples, string $startStr, string $endStr): array
    {
        $startStr = strtoupper(trim($startStr));
        $endStr   = strtoupper(trim($endStr));

        if ($startStr === $endStr) {
            return $samples->filter(fn($s) => strtoupper(trim((string) $s->sample_number)) === $startStr)
                ->pluck('id')->toArray();
        }

        $extractNum = function ($str) {
            if (preg_match('/(\d+)/', $str, $matches)) {
                return (int) $matches[1];
            }
            return null;
        };

        $numStart = $extractNum($startStr);
        $numEnd   = $extractNum($endStr);

        if ($numStart !== null && $numEnd !== null && $numStart > $numEnd) {
            $tmp = $numStart;
            $numStart = $numEnd;
            $numEnd = $tmp;
        }

        $matched = [];
        $inRange = false;

        foreach ($samples as $sample) {
            $numStr = strtoupper(trim((string) $sample->sample_number));
            $numVal = $extractNum($numStr);

            if ($numStart !== null && $numEnd !== null && $numVal !== null) {
                if ($numVal >= $numStart && $numVal <= $numEnd) {
                    $matched[] = $sample->id;
                }
            } else {
                if ($numStr === $startStr || $numStr === $endStr) {
                    $inRange = !$inRange;
                    $matched[] = $sample->id;
                    continue;
                }
                if ($inRange) {
                    $matched[] = $sample->id;
                }
            }
        }

        return $matched;
    }

    public function getPendingQcPhotosCountProperty(): int
    {
        return $this->getBarrenoMetrics()['pending_qc'];
    }

    #[On('qc-photos-updated')]
    public function onQcPhotosUpdated(): void
    {
        $this->cachedBarrenoMetrics = null;
        $this->flushCachedTableRecords();
    }

    public function navigateEditSample(int|string $targetSampleId): void
    {
        $this->replaceMountedAction('edit', context: [
            'table' => true,
            'recordKey' => (string) $targetSampleId,
        ]);
    }

    public static function getOrderedBarrenoSampleIds(int $barrenoId): array
    {
        return DrillHoleSample::where('barreno_id', $barrenoId)
            ->orderByRaw('CASE WHEN from_depth IS NULL THEN 1 ELSE 0 END, from_depth ASC, id ASC')
            ->pluck('id')
            ->toArray();
    }

    public static function evaluateSampleInterval(
        DrillHoleSample $record,
        mixed $fromVal,
        mixed $toVal,
        mixed $recupVal = null,
        mixed $weightVal = null
    ): array {
        $result = [
            'is_valid' => true,
            'length' => 0.0,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'prev_sample' => null,
            'next_sample' => null,
        ];

        if ($record->sample_type === 'Control') {
            $result['info'][] = 'Muestra de Control QA/QC (no requiere intervalos de perforación).';
            return $result;
        }

        $from = is_numeric($fromVal) ? (float) $fromVal : null;
        $to = is_numeric($toVal) ? (float) $toVal : null;
        $recup = is_numeric($recupVal) ? (float) $recupVal : null;
        $weight = is_numeric($weightVal) ? (float) $weightVal : null;

        if ($from === null || $to === null) {
            $result['errors'][] = 'Los campos Desde (FROM) y Hasta (TO) son obligatorios.';
            $result['is_valid'] = false;
            return $result;
        }

        if ($from < 0) {
            $result['errors'][] = "La profundidad 'Desde' ({$from} m) no puede ser negativa.";
        }

        if ($from >= $to) {
            $result['errors'][] = "La profundidad 'Desde' ({$from} m) debe ser estrictamente menor que 'Hasta' ({$to} m).";
        }

        $length = round($to - $from, 2);
        $result['length'] = $length;

        if ($length > 0 && $recup !== null && $recup > $length + 0.001) {
            $result['errors'][] = "La recuperación ({$recup} m) no puede exceder la longitud del tramo ({$length} m).";
        }

        $barreno = $record->barreno ?: DrillHole::find($record->barreno_id);
        $startDepth = (float) ($barreno?->start_depth ?? 0.0);

        if ($from < $startDepth - 0.001) {
            $result['errors'][] = "La profundidad 'Desde' ({$from} m) es menor a la profundidad inicial del barreno ({$startDepth} m).";
        }

        if ($barreno?->max_depth !== null && (float)$barreno->max_depth > 0 && $to > (float)$barreno->max_depth + 0.001) {
            $result['errors'][] = "La profundidad 'Hasta' ({$to} m) excede la profundidad máxima del barreno ({$barreno->max_depth} m).";
        }

        // Obtener otras muestras del barreno con from/to definidos
        $otherOriginals = DrillHoleSample::where('barreno_id', $record->barreno_id)
            ->where('id', '!=', $record->id)
            ->whereNotNull('from_depth')
            ->whereNotNull('to_depth')
            ->orderBy('from_depth')
            ->get();

        // 1. Detectar traslapes
        $overlapping = $otherOriginals->filter(function ($other) use ($from, $to) {
            $oFrom = (float) $other->from_depth;
            $oTo = (float) $other->to_depth;
            return ($from < $oTo - 0.001) && ($to > $oFrom + 0.001);
        });

        foreach ($overlapping as $overlap) {
            $result['errors'][] = "Traslape con muestra #{$overlap->sample_number} ({$overlap->from_depth}m - {$overlap->to_depth}m).";
        }

        // 2. Detectar GAPs
        // Muestra anterior
        $prevSample = $otherOriginals->filter(fn($o) => (float)$o->to_depth <= $from + 0.001)->sortByDesc('to_depth')->first();
        $result['prev_sample'] = $prevSample;

        $gapValidations = DrillHoleGapValidation::where('barreno_id', $record->barreno_id)->get();

        if ($prevSample) {
            $gapBefore = round($from - (float)$prevSample->to_depth, 3);
            if ($gapBefore > 0.011) {
                $isValidated = $gapValidations->contains(function ($gv) use ($prevSample, $from) {
                    return ((float)$gv->from_depth <= (float)$prevSample->to_depth + 0.01) &&
                           ((float)$gv->to_depth >= $from - 0.01);
                });
                if (!$isValidated) {
                    $result['warnings'][] = "GAP de {$gapBefore} m detectado entre muestra anterior #{$prevSample->sample_number} ({$prevSample->to_depth}m) y este tramo ({$from}m) sin validar.";
                }
            }
        } else {
            // Es la primer muestra: verificar GAP inicial
            $gapInitial = round($from - $startDepth, 3);
            if ($gapInitial > 0.011) {
                $isValidated = $gapValidations->contains(function ($gv) use ($startDepth, $from) {
                    return ((float)$gv->from_depth <= $startDepth + 0.01) &&
                           ((float)$gv->to_depth >= $from - 0.01);
                });
                if (!$isValidated) {
                    $result['warnings'][] = "GAP inicial de {$gapInitial} m desde inicio ({$startDepth}m) hasta {$from}m sin validar.";
                }
            }
        }

        // Muestra posterior
        $nextSample = $otherOriginals->filter(fn($o) => (float)$o->from_depth >= $to - 0.001)->sortBy('from_depth')->first();
        $result['next_sample'] = $nextSample;

        if ($nextSample) {
            $gapAfter = round((float)$nextSample->from_depth - $to, 3);
            if ($gapAfter > 0.011) {
                $isValidatedNext = $gapValidations->contains(function ($gv) use ($to, $nextSample) {
                    return ((float)$gv->from_depth <= $to + 0.01) &&
                           ((float)$gv->to_depth >= (float)$nextSample->from_depth - 0.01);
                });
                if (!$isValidatedNext) {
                    $result['warnings'][] = "GAP de {$gapAfter} m detectado entre este tramo ({$to}m) y la siguiente muestra #{$nextSample->sample_number} ({$nextSample->from_depth}m) sin validar.";
                }
            }
        }

        // 3. Validación de peso
        if ($weight !== null && $weight > 0) {
            $settings = SampleSetting::getSettings();
            $minWeight = (float) ($settings->min_sample_weight ?? 0.50);
            $maxWeight = (float) ($settings->max_sample_weight ?? 15.00);

            if ($weight < $minWeight) {
                $result['warnings'][] = "El peso ({$weight} kg) es inferior al mínimo recomendado ({$minWeight} kg).";
            } elseif ($weight > $maxWeight) {
                $result['warnings'][] = "El peso ({$weight} kg) supera el máximo recomendado ({$maxWeight} kg).";
            }
        }

        if (!empty($result['errors'])) {
            $result['is_valid'] = false;
        } elseif (empty($result['warnings'])) {
            $result['info'][] = "✓ Intervalo continuo y coherente ({$length} m). Sin traslapes detectados.";
        }

        return $result;
    }

    public static function renderValidationLiveStatusHtml(array $eval): HtmlString
    {
        $html = '<div class="ac-modal-status-container" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem;">';

        if (!empty($eval['errors'])) {
            $html .= '<div class="ac-modal-status-box status-error" style="padding: 0.75rem; border-radius: 0.5rem; background-color: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;">';
            $html .= '<div class="ac-modal-status-header" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.025em; color: #b91c1c;">';
            $html .= '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; max-width: 16px; max-height: 16px; flex-shrink: 0; color: #dc2626;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
            $html .= '<span>Errores detectados (Bloquean guardado)</span>';
            $html .= '</div>';
            $html .= '<ul class="ac-modal-status-list" style="margin-top: 0.375rem; margin-bottom: 0; padding-left: 1.25rem; list-style-type: disc; font-size: 0.75rem; color: #dc2626;">';
            foreach ($eval['errors'] as $err) {
                $html .= '<li>' . e($err) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if (!empty($eval['warnings'])) {
            $html .= '<div class="ac-modal-status-box status-warning" style="padding: 0.75rem; border-radius: 0.5rem; background-color: #fffbeb; border: 1px solid #fcd34d; color: #92400e;">';
            $html .= '<div class="ac-modal-status-header" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.025em; color: #b45309;">';
            $html .= '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; max-width: 16px; max-height: 16px; flex-shrink: 0; color: #d97706;"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
            $html .= '<span>Avisos de Continuidad y GAP</span>';
            $html .= '</div>';
            $html .= '<ul class="ac-modal-status-list" style="margin-top: 0.375rem; margin-bottom: 0; padding-left: 1.25rem; list-style-type: disc; font-size: 0.75rem; color: #b45309;">';
            foreach ($eval['warnings'] as $warn) {
                $html .= '<li>' . e($warn) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        if (empty($eval['errors']) && empty($eval['warnings']) && !empty($eval['info'])) {
            $html .= '<div class="ac-modal-status-box status-success" style="padding: 0.625rem 0.75rem; border-radius: 0.5rem; background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;">';
            $html .= '<div class="ac-modal-status-header" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 0.75rem; color: #047857;">';
            $html .= '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; min-width: 16px; min-height: 16px; max-width: 16px; max-height: 16px; flex-shrink: 0; color: #059669;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
            $html .= '<span>' . e(implode(' ', $eval['info'])) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    public static function renderNavigationHeaderHtml(DrillHoleSample $record): HtmlString
    {
        $allIds = static::getOrderedBarrenoSampleIds($record->barreno_id);
        $currentIndex = array_search($record->id, $allIds);
        $total = count($allIds);
        $currentIndexDisplay = $currentIndex !== false ? $currentIndex + 1 : 1;

        $isControl = $record->sample_type === 'Control';
        $typeLabel = $isControl ? 'Control QA/QC' : 'Original';
        if ($isControl && !empty($record->control_type)) {
            $typeLabel = 'Control (' . $record->control_type . ')';
        }
        $badgeClass = $isControl ? 'ac-badge-control' : 'ac-badge-original';
        $badgeInlineStyle = $isControl
            ? 'background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;'
            : 'background-color: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;';

        $progressPct = $total > 0 ? round(($currentIndexDisplay / $total) * 100) : 0;

        $html = '
        <div class="ac-modal-nav-container" style="display: flex; align-items: center; justify-content: center; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background-color: rgba(248, 250, 252, 0.95); margin-bottom: 0.75rem; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
            <div class="ac-modal-sample-center" style="text-align: center; width: 100%;">
                <div class="ac-modal-sample-counter" style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;">
                    MUESTRA ' . $currentIndexDisplay . ' DE ' . $total . ' (' . $progressPct . '%)
                </div>
                <div class="ac-modal-sample-details" style="display: flex; align-items: center; justify-content: center; gap: 0.625rem; margin-top: 0.25rem;">
                    <span class="ac-modal-sample-num" style="font-weight: 800; font-size: 1.1rem; color: #1e293b; letter-spacing: -0.01em;">#' . e($record->sample_number) . '</span>
                    <span class="ac-modal-sample-badge ' . $badgeClass . '" style="display: inline-block; padding: 0.15rem 0.6rem; font-size: 0.7rem; font-weight: 700; border-radius: 9999px; ' . $badgeInlineStyle . '">' . $typeLabel . '</span>
                </div>
            </div>
        </div>';

        return new HtmlString($html);
    }
}
