<?php

namespace App\Filament\Resources\DrillHoles\Schemas;

use App\Models\DrillHole;
use App\Services\DrillHolePdfParserService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class DrillHoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $canEditAdvanced = auth()->user()?->hasAnyRole(['super_admin', 'super-admin', 'Administrador', 'Admin', 'Supervisor Coreshack', 'Supervisor']) ?? false;

        return $schema
            ->columns(2)
            ->components([

                // ═══════════════════════════════════════════════════════════
                // COLUMNA IZQUIERDA
                // ═══════════════════════════════════════════════════════════
                Group::make([
                    // 1. Identificación y Categorización (Card Superior Izquierda)
                    Section::make('Identificación y Categorización')
                        ->description('Datos de identificación del barreno, su ubicación y zona objetivo.')
                        ->icon('heroicon-o-identification')
                        ->columns(2)
                        ->schema([
                            TextInput::make('nombre_barreno')
                                ->label('ID de Barreno')
                                ->placeholder('Ej. DDH-001 / MLE26-039')
                                ->required()
                                ->maxLength(100)
                                ->unique(table: 'drill_holes', column: 'nombre_barreno', ignoreRecord: true)
                                ->validationMessages([
                                    'unique' => 'Este ID de barreno ya existe en el sistema.',
                                ])
                                ->columnSpan(2),

                            Toggle::make('is_child')
                                ->label('¿Es Barreno Hijo (Wedge / Continuación)?')
                                ->helperText('Activa si este barreno es un desvío, cuña (wedge) o continuación de una perforación madre.')
                                ->default(false)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (!$state) {
                                        $set('parent_id', null);
                                    }
                                })
                                ->columnSpan(2),

                            Select::make('parent_id')
                                ->label('Barreno Madre (Padre)')
                                ->placeholder('Selecciona el barreno madre de origen...')
                                ->options(function (?DrillHole $record) {
                                    return DrillHole::query()
                                        ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->orderBy('nombre_barreno')
                                        ->pluck('nombre_barreno', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->visible(fn ($get) => (bool) $get('is_child'))
                                ->required(fn ($get) => (bool) $get('is_child'))
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if (empty($state)) {
                                        return;
                                    }

                                    $parent = DrillHole::find($state);
                                    if (!$parent) {
                                        return;
                                    }

                                    // 1. Heredar Datos Instalados
                                    if ($parent->easting !== null) $set('easting', $parent->easting);
                                    if ($parent->northing !== null) $set('northing', $parent->northing);
                                    if ($parent->elevation !== null) $set('elevation', $parent->elevation);
                                    if ($parent->dip !== null) $set('dip', $parent->dip);
                                    if ($parent->azimuth !== null) $set('azimuth', $parent->azimuth);

                                    // 2. Heredar Datos del Reporte Topográfico
                                    if (!empty($parent->survey_pdf_path)) $set('survey_pdf_path', $parent->survey_pdf_path);
                                    if (!empty($parent->survey_responsible)) $set('survey_responsible', $parent->survey_responsible);
                                    if (!empty($parent->survey_date)) $set('survey_date', $parent->survey_date?->format('Y-m-d') ?? $parent->survey_date);
                                    if (!empty($parent->planilla)) $set('planilla', $parent->planilla);

                                    // 3. Heredar Profundidad Inicial = Profundidad Máxima de la Madre (Abierta a edición)
                                    if ($parent->max_depth !== null) {
                                        $set('start_depth', $parent->max_depth);
                                    }

                                    // 4. Heredar Distrito, Proyecto y Target
                                    if ($parent->sede_id) $set('sede_id', $parent->sede_id);
                                    if ($parent->proyecto_id) $set('proyecto_id', $parent->proyecto_id);
                                    if ($parent->target) $set('target', $parent->target);

                                    // 5. Heredar Atributos Planeados (Abiertos a edición)
                                    if ($parent->planned_easting !== null) $set('planned_easting', $parent->planned_easting);
                                    if ($parent->planned_northing !== null) $set('planned_northing', $parent->planned_northing);
                                    if ($parent->planned_elevation !== null) $set('planned_elevation', $parent->planned_elevation);
                                    if ($parent->planned_dip !== null) $set('planned_dip', $parent->planned_dip);
                                    if ($parent->planned_azimuth !== null) $set('planned_azimuth', $parent->planned_azimuth);
                                    if ($parent->drilling_type) $set('drilling_type', $parent->drilling_type);

                                    Notification::make()
                                        ->title('Datos Heredados de la Madre')
                                        ->body("Se precargaron coordenadas, topografía, proyecto y profundidad inicial ({$parent->max_depth}m) desde el barreno {$parent->nombre_barreno}.")
                                        ->success()
                                        ->send();
                                })
                                ->columnSpan(2),

                            Select::make('target')
                                ->label('Target / Objetivo')
                                ->placeholder('Selecciona un target...')
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->options([
                                    'MLE' => 'MLE - Media Luna East',
                                    'MLW' => 'MLW - Media Luna West',
                                    'NAR' => 'NAR - Naranjo',
                                    'TDS' => 'TDS - Todos Santos',
                                    'ATZ' => 'ATZ - Atzcala',
                                ])
                                ->columnSpan(2),

                            Select::make('sede_id')
                                ->label('Distrito')
                                ->placeholder('Selecciona un distrito...')
                                ->relationship('sede', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(1),

                            Select::make('proyecto_id')
                                ->label('Proyecto')
                                ->placeholder('Selecciona un proyecto...')
                                ->relationship('proyecto', 'nombre')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(1),
                        ]),

                    // 2. Especificaciones Técnicas (Card Inferior Izquierda)
                    Section::make('Especificaciones Técnicas')
                        ->description('Parámetros técnicos de la perforación.')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->disabled(!$canEditAdvanced)
                        ->columns(2)
                        ->schema([
                            TextInput::make('start_depth')
                                ->label('Profundidad Inicial')
                                ->placeholder('0.00')
                                ->numeric()
                                ->default(0.00)
                                ->minValue(0)
                                ->maxValue(999999.99)
                                ->step(0.01)
                                ->suffix('m')
                                ->columnSpan(1),

                            TextInput::make('max_depth')
                                ->label('Profundidad Máxima')
                                ->placeholder('Ej. 350.00')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(999999.99)
                                ->step(0.01)
                                ->suffix('m')
                                ->columnSpan(1),

                            Select::make('drilling_type')
                                ->label('Tipo de Perforación')
                                ->placeholder('Selecciona un tipo...')
                                ->native(false)
                                ->options([
                                    'Diamantina' => 'Diamantina (DDH)',
                                    'RC' => 'Circulación Reversa (RC)',
                                    'Rotary' => 'Rotaria (Rotary)',
                                    'Aire_Reverso' => 'Aire Reverso (AR)',
                                    'Percusion' => 'Percusión',
                                    'Sonic' => 'Sónico',
                                ])
                                ->columnSpan(2),

                            /*
                            // Tamaño de Núcleo (Oculto por requerimiento del cliente)
                            Select::make('core_size')
                                ->label('Tamaño de Núcleo')
                                ->placeholder('Selecciona un tamaño...')
                                ->native(false)
                                ->options([
                                    'BQ'  => 'BQ  (36.5 mm)',
                                    'NQ'  => 'NQ  (47.6 mm)',
                                    'NQ2' => 'NQ2 (50.5 mm)',
                                    'HQ'  => 'HQ  (63.5 mm)',
                                    'HQ3' => 'HQ3 (61.1 mm)',
                                    'PQ'  => 'PQ  (83.0 mm)',
                                    'N/A' => 'N/A (No Aplica)',
                                ]),

                            // Propósito / Objetivo (Oculto por requerimiento del cliente)
                            TextInput::make('purpose')
                                ->label('Propósito / Objetivo')
                                ->placeholder('Ej. Exploración de mineralización aurífera')
                                ->maxLength(255),
                            */
                        ]),
                ])->columnSpan(1),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA DERECHA
                // ═══════════════════════════════════════════════════════════
                Group::make([
                    // 3. Reporte de Levantamiento y Atributos Instalados (Card Superior Derecha)
                    Section::make('Reporte de Levantamiento y Atributos Instalados')
                        ->description('Carga del informe oficial en PDF y coordenadas reales.')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->disabled(!$canEditAdvanced)
                        ->columns(3)
                        ->schema([
                            FileUpload::make('survey_pdf_path')
                                ->label('Informe de Levantamiento / Verificación (PDF)')
                                ->disk('public')
                                ->directory('survey_reports')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(10240) // 10MB
                                ->live()
                                ->columnSpanFull()
                                ->helperText('Sube el informe PDF del topógrafo para extraer automáticamente los atributos instalados y habilitar su edición.')
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    if (empty($state)) {
                                        return;
                                    }

                                    $filePath = is_array($state) ? reset($state) : $state;

                                    if (!$filePath) {
                                        return;
                                    }

                                    try {
                                        $parser = app(DrillHolePdfParserService::class);
                                        $extracted = $parser->parsePdf($filePath);

                                        if (empty($extracted)) {
                                            Notification::make()
                                                ->title('Aviso de Lectura de PDF')
                                                ->body('No se encontraron tablas de atributos en el PDF subido. Puedes ingresar los datos manualmente.')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        $targetPlanilla = !empty($extracted['barreno_id']) ? $extracted['barreno_id'] : ($extracted['planilla'] ?? null);
                                        if (!empty($targetPlanilla)) {
                                            $set('planilla', $targetPlanilla);
                                        }
                                        if (!empty($extracted['responsible'])) {
                                            $set('survey_responsible', $extracted['responsible']);
                                        }
                                        if (!empty($extracted['survey_date'])) {
                                            $set('survey_date', $extracted['survey_date']);
                                        }

                                        if (!empty($extracted['installed'])) {
                                            $inst = $extracted['installed'];
                                            if (isset($inst['easting']))
                                                $set('easting', $inst['easting']);
                                            if (isset($inst['northing']))
                                                $set('northing', $inst['northing']);
                                            if (isset($inst['elevation']))
                                                $set('elevation', $inst['elevation']);
                                            if (isset($inst['dip']))
                                                $set('dip', $inst['dip']);
                                            if (isset($inst['azimuth']))
                                                $set('azimuth', $inst['azimuth']);
                                        }

                                        if (!empty($extracted['planned'])) {
                                            $pl = $extracted['planned'];
                                            if (isset($pl['easting']) && empty($get('planned_easting')))
                                                $set('planned_easting', $pl['easting']);
                                            if (isset($pl['northing']) && empty($get('planned_northing')))
                                                $set('planned_northing', $pl['northing']);
                                            if (isset($pl['elevation']) && empty($get('planned_elevation')))
                                                $set('planned_elevation', $pl['elevation']);
                                            if (isset($pl['dip']) && empty($get('planned_dip')))
                                                $set('planned_dip', $pl['dip']);
                                            if (isset($pl['azimuth']) && empty($get('planned_azimuth')))
                                                $set('planned_azimuth', $pl['azimuth']);
                                        }

                                        Notification::make()
                                            ->title('PDF Procesado con Éxito')
                                            ->body('Se han extraído y cargado automáticamente los atributos del reporte de levantamiento.')
                                            ->success()
                                            ->send();

                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->title('Error al procesar el PDF')
                                            ->body('No se pudo analizar el contenido del archivo subido: ' . $e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),

                            TextInput::make('planilla')
                                ->label('Planilla / ID Informe')
                                ->placeholder('Ej. MLE-04')
                                ->maxLength(100)
                                ->columnSpan(1),

                            TextInput::make('survey_responsible')
                                ->label('Responsable Levantamiento')
                                ->placeholder('Ej. ING. RIGOBERTO MALDONADO MORENO')
                                ->maxLength(255)
                                ->columnSpan(1),

                            DatePicker::make('survey_date')
                                ->label('Fecha del Levantamiento')
                                ->displayFormat('d/m/Y')
                                ->columnSpan(1),

                            TextInput::make('easting')
                                ->label('Este Instalado')
                                ->placeholder('Ej. 423282.288')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(9999999.999)
                                ->step(0.001)
                                ->suffix('m')
                                ->disabled(fn(Get $get) => empty($get('survey_pdf_path')))
                                ->dehydrated()
                                ->helperText(fn(Get $get) => empty($get('survey_pdf_path')) ? 'Bloqueado: Requiere subir archivo PDF.' : null)
                                ->columnSpan(1),

                            TextInput::make('northing')
                                ->label('Norte Instalado')
                                ->placeholder('Ej. 1984057.169')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(9999999.999)
                                ->step(0.001)
                                ->suffix('m')
                                ->disabled(fn(Get $get) => empty($get('survey_pdf_path')))
                                ->dehydrated()
                                ->helperText(fn(Get $get) => empty($get('survey_pdf_path')) ? 'Bloqueado: Requiere subir archivo PDF.' : null)
                                ->columnSpan(1),

                            TextInput::make('elevation')
                                ->label('Elevación Instalada')
                                ->placeholder('Ej. 1143.176')
                                ->numeric()
                                ->maxValue(999999.99)
                                ->step(0.01)
                                ->suffix('m.s.n.m.')
                                ->disabled(fn(Get $get) => empty($get('survey_pdf_path')))
                                ->dehydrated()
                                ->helperText(fn(Get $get) => empty($get('survey_pdf_path')) ? 'Bloqueado: Requiere subir archivo PDF.' : null)
                                ->columnSpan(1),

                            TextInput::make('dip')
                                ->label('Dip Instalado')
                                ->placeholder('Ej. -75.45')
                                ->numeric()
                                ->minValue(-90)
                                ->maxValue(90)
                                ->step(0.01)
                                ->suffix('°')
                                ->disabled(fn(Get $get) => empty($get('survey_pdf_path')))
                                ->dehydrated()
                                ->helperText(fn(Get $get) => empty($get('survey_pdf_path')) ? 'Bloqueado: Requiere subir archivo PDF.' : null)
                                ->columnSpan(1),

                            TextInput::make('azimuth')
                                ->label('Azimuth Instalado')
                                ->placeholder('Ej. 46.54')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(360)
                                ->step(0.01)
                                ->suffix('°')
                                ->disabled(fn(Get $get) => empty($get('survey_pdf_path')))
                                ->dehydrated()
                                ->helperText(fn(Get $get) => empty($get('survey_pdf_path')) ? 'Bloqueado: Requiere subir archivo PDF.' : null)
                                ->columnSpan(1),
                        ]),

                    // 4. Atributos Planeados (Card Inferior Derecha)
                    Section::make('Atributos Planeados')
                        ->description('Coordenadas y orientación teórica o de diseño del barreno.')
                        ->icon('heroicon-o-document-chart-bar')
                        ->collapsible()
                        ->disabled(!$canEditAdvanced)
                        ->columns(3)
                        ->schema([
                            TextInput::make('planned_easting')
                                ->label('Este Planeado')
                                ->placeholder('Ej. 423282.000')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(9999999.999)
                                ->step(0.001)
                                ->suffix('m')
                                ->columnSpan(1),

                            TextInput::make('planned_northing')
                                ->label('Norte Planeado')
                                ->placeholder('Ej. 1984057.000')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(9999999.999)
                                ->step(0.001)
                                ->suffix('m')
                                ->columnSpan(1),

                            TextInput::make('planned_elevation')
                                ->label('Elevación Planeada')
                                ->placeholder('Ej. 1143.00')
                                ->numeric()
                                ->maxValue(999999.99)
                                ->step(0.01)
                                ->suffix('m.s.n.m.')
                                ->columnSpan(1),

                            TextInput::make('planned_dip')
                                ->label('Dip Planeado')
                                ->placeholder('Ej. -75.00')
                                ->numeric()
                                ->minValue(-90)
                                ->maxValue(90)
                                ->step(0.01)
                                ->suffix('°')
                                ->helperText('Rango: -90° a 90°')
                                ->columnSpan(1),

                            TextInput::make('planned_azimuth')
                                ->label('Azimuth Planeado')
                                ->placeholder('Ej. 45.00')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(360)
                                ->step(0.01)
                                ->suffix('°')
                                ->helperText('Rango: 0° a 360°')
                                ->columnSpan(1),
                        ]),
                ])->columnSpan(1),

            ]);
    }
}
