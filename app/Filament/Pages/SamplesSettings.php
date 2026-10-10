<?php

namespace App\Filament\Pages;

use App\Models\SampleSetting;
use App\Models\User;
use App\Notifications\SampleSettingsUpdatedNotification;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Notification as FacadesNotification;

class SamplesSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Configuración de Muestras';

    protected static ?string $title = 'Configuración de Muestras';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.samples-settings';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'Admin CoreFlow', 'Supervisor CoreS'])
            || $user->can('View:SamplesSettings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $settings = SampleSetting::getSettings();
        $this->form->fill([
            'max_sack_weight'   => $settings->max_sack_weight,
            'min_sample_weight' => $settings->min_sample_weight,
            'max_sample_weight' => $settings->max_sample_weight,
            'factor_pq'        => $settings->factor_pq,
            'factor_hq'        => $settings->factor_hq,
            'factor_nq'        => $settings->factor_nq,
            'factor_bq'        => $settings->factor_bq,
            'blank_weight'     => $settings->blank_weight,
            'standard_weight'  => $settings->standard_weight,
            'duplicate_ratio'  => $settings->duplicate_ratio,
            'require_qc_photos' => (bool) ($settings->require_qc_photos ?? true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Límites de Peso de Muestras y Costales')
                    ->description('Parámetros globales aplicados a la importación de datos, captura ágil y empaquetado de despachos.')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('max_sack_weight')
                                    ->label('Peso Máximo por Costal')
                                    ->suffix('kg')
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(1.0)
                                    ->maxValue(100.0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText('Capacidad máxima de acumulación de peso por saco de despacho.')
                                    ->prefixIcon('heroicon-o-archive-box'),

                                TextInput::make('min_sample_weight')
                                    ->label('Peso Mínimo de Muestra')
                                    ->suffix('kg')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.01)
                                    ->maxValue(15.0)
                                    ->required()
                                    ->helperText('Límite inferior permitido para el peso de una muestra individual.')
                                    ->prefixIcon('heroicon-o-beaker'),

                                TextInput::make('max_sample_weight')
                                    ->label('Peso Máximo de Muestra')
                                    ->suffix('kg')
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(0.5)
                                    ->maxValue(50.0)
                                    ->required()
                                    ->rule(fn (Get $get) => 'gt:' . ((float) ($get('min_sample_weight') ?: 0.01)))
                                    ->helperText('Límite superior permitido para el peso de una muestra individual.')
                                    ->prefixIcon('heroicon-o-beaker'),
                            ]),
                    ]),

                Section::make('Factores de Estimación de Peso por Diámetro (Generación de Costales)')
                    ->description('Factores teóricos de peso por metro lineal (kg/m) utilizados para empaquetar costales cuando las muestras no cuentan con peso físico de báscula.')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('factor_pq')
                                    ->label('Factor PQ (85.0 mm)')
                                    ->suffix('kg/m')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(function (Get $get) {
                                        $max = (float) ($get('max_sack_weight') ?: 25.0);
                                        $fac = (float) ($get('factor_pq') ?: 7.66);
                                        $m = $fac > 0 ? round($max / $fac, 1) : 0;
                                        return "Equivale a ~{$m} m por costal de {$max} kg";
                                    })
                                    ->prefixIcon('heroicon-o-scale'),

                                TextInput::make('factor_hq')
                                    ->label('Factor HQ (63.5 mm)')
                                    ->suffix('kg/m')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(function (Get $get) {
                                        $max = (float) ($get('max_sack_weight') ?: 25.0);
                                        $fac = (float) ($get('factor_hq') ?: 4.28);
                                        $m = $fac > 0 ? round($max / $fac, 1) : 0;
                                        return "Equivale a ~{$m} m por costal de {$max} kg";
                                    })
                                    ->prefixIcon('heroicon-o-scale'),

                                TextInput::make('factor_nq')
                                    ->label('Factor NQ (47.6 mm)')
                                    ->suffix('kg/m')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(function (Get $get) {
                                        $max = (float) ($get('max_sack_weight') ?: 25.0);
                                        $fac = (float) ($get('factor_nq') ?: 2.40);
                                        $m = $fac > 0 ? round($max / $fac, 1) : 0;
                                        return "Equivale a ~{$m} m por costal de {$max} kg";
                                    })
                                    ->prefixIcon('heroicon-o-scale'),

                                TextInput::make('factor_bq')
                                    ->label('Factor BQ (36.4 mm)')
                                    ->suffix('kg/m')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(function (Get $get) {
                                        $max = (float) ($get('max_sack_weight') ?: 25.0);
                                        $fac = (float) ($get('factor_bq') ?: 1.40);
                                        $m = $fac > 0 ? round($max / $fac, 1) : 0;
                                        return "Equivale a ~{$m} m por costal de {$max} kg";
                                    })
                                    ->prefixIcon('heroicon-o-scale'),
                            ]),
                    ]),

                Section::make('Estimación para Muestras de Control (QA/QC)')
                    ->description('Valores aplicados a muestras de control de calidad para el cálculo y balance de los costales de despacho.')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('blank_weight')
                                    ->label('Peso Muestra Blanco (BLANK)')
                                    ->suffix('kg')
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(0.1)
                                    ->maxValue(25.0)
                                    ->required()
                                    ->helperText('Peso de bolsa de material estéril grueso.')
                                    ->prefixIcon('heroicon-o-archive-box'),

                                TextInput::make('standard_weight')
                                    ->label('Peso Muestra Estándar (CRM)')
                                    ->suffix('kg')
                                    ->numeric()
                                    ->step(0.001)
                                    ->minValue(0.001)
                                    ->maxValue(5.0)
                                    ->required()
                                    ->helperText('Peso de sobre de pulpa certificada (~65 g).')
                                    ->prefixIcon('heroicon-o-sparkles'),

                                TextInput::make('duplicate_ratio')
                                    ->label('Proporción de Duplicado')
                                    ->suffix('%')
                                    ->numeric()
                                    ->step(1)
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->required()
                                    ->helperText('Porcentaje respecto al peso original (50% = 1/4 de núcleo).')
                                    ->prefixIcon('heroicon-o-document-duplicate'),
                            ]),
                    ]),

                Section::make('Control de Calidad (Evidencia Fotográfica)')
                    ->description('Políticas y requisitos de fotografías para muestras de control de calidad.')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        Toggle::make('require_qc_photos')
                            ->label('Exigir 2 fotografías en muestras de control (báscula y circundantes)')
                            ->helperText('Si está activo, ninguna Work Order podrá enviarse al laboratorio hasta que todas sus muestras de control (estándares y blancos) cuenten con sus 2 fotografías registradas.')
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $settings = SampleSetting::getSettings();
        $settings->update([
            'max_sack_weight'   => $state['max_sack_weight'],
            'min_sample_weight' => $state['min_sample_weight'],
            'max_sample_weight' => $state['max_sample_weight'],
            'factor_pq'        => $state['factor_pq'],
            'factor_hq'        => $state['factor_hq'],
            'factor_nq'        => $state['factor_nq'],
            'factor_bq'        => $state['factor_bq'],
            'blank_weight'     => $state['blank_weight'],
            'standard_weight'  => $state['standard_weight'],
            'duplicate_ratio'  => $state['duplicate_ratio'],
            'require_qc_photos' => (bool) ($state['require_qc_photos'] ?? true),
        ]);

        // Actualizar también la configuración en runtime de Laravel
        config(['coreflow.max_sack_weight' => (float) $state['max_sack_weight']]);

        Notification::make()
            ->title('Configuración guardada')
            ->body('Los límites de peso de muestras, costales y factores de estimación fueron actualizados correctamente.')
            ->success()
            ->send();

        // Notificación persistente a campana para Administradores
        $adminUsers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['super_admin', 'Admin CoreFlow', 'Administrador', 'Admin']);
        })->get();

        if ($adminUsers->isNotEmpty()) {
            FacadesNotification::send(
                $adminUsers,
                new SampleSettingsUpdatedNotification(auth()->user()?->name ?? 'Usuario')
            );
        }

        $this->dispatch('databaseNotificationsSent');
    }
}
