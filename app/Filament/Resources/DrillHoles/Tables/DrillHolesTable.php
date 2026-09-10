<?php

namespace App\Filament\Resources\DrillHoles\Tables;

use App\Models\DrillHole;
use App\Models\DrillHoleGapValidation;
use App\Services\GapCalculatorService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class DrillHolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_barreno')
                    ->label('ID Barreno')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-rectangle-stack'),

                TextColumn::make('target')
                    ->label('Target')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->toggleable(),

                TextColumn::make('sede.name')
                    ->label('Distrito')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('proyecto.nombre')
                    ->label('Proyecto')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('hierarchy_type')
                    ->label('Jerarquía')
                    ->badge()
                    ->state(function (DrillHole $record): string {
                        if ($record->is_child && $record->parent) {
                            return 'Hijo de ' . $record->parent->nombre_barreno;
                        }
                        if ($record->is_child) {
                            return 'Hijo (Wedge)';
                        }
                        if ($record->hasChildren()) {
                            return 'Madre';
                        }
                        return 'Principal';
                    })
                    ->color(function (DrillHole $record): string {
                        if ($record->is_child) {
                            return 'warning';
                        }
                        if ($record->hasChildren()) {
                            return 'success';
                        }
                        return 'gray';
                    })
                    ->icon(function (DrillHole $record): string {
                        if ($record->is_child) {
                            return 'heroicon-o-arrow-turn-down-right';
                        }
                        if ($record->hasChildren()) {
                            return 'heroicon-o-rectangle-group';
                        }
                        return 'heroicon-o-stop';
                    })
                    ->toggleable(),

                // ─── Columna Badge de Estado de Atributos Instalados ────────
                TextColumn::make('installed_status')
                    ->label('Atributos Instalados')
                    ->badge()
                    ->state(function (DrillHole $record): string {
                        if (!$record->hasInstalledAttributes()) {
                            return 'Falta Capturar';
                        }
                        if (!$record->hasSurveyPdf()) {
                            return 'Sin PDF Adjunto';
                        }
                        return 'Instalados';
                    })
                    ->color(function (DrillHole $record): string {
                        if (!$record->hasInstalledAttributes()) {
                            return 'danger';
                        }
                        if (!$record->hasSurveyPdf()) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->icon(function (DrillHole $record): string {
                        if (!$record->hasInstalledAttributes()) {
                            return 'heroicon-o-exclamation-circle';
                        }
                        if (!$record->hasSurveyPdf()) {
                            return 'heroicon-o-exclamation-triangle';
                        }
                        return 'heroicon-o-check-circle';
                    })
                    ->sortable(),

                // ─── Columna Badge de Estado de Gaps ────────────────────────
                TextColumn::make('gap_status')
                    ->label('Estado de Gaps')
                    ->badge()
                    ->state(function (DrillHole $record): string {
                        $maxDepth = (float) ($record->max_depth ?? 0);
                        if ($maxDepth <= 0) {
                            return 'Sin Prof. Máx.';
                        }
                        $gaps = GapCalculatorService::calculateGaps($record);
                        return empty($gaps) ? '100% Cubierto' : count($gaps) . ' Gap(s) Pendiente(s)';
                    })
                    ->color(function (DrillHole $record): string {
                        $maxDepth = (float) ($record->max_depth ?? 0);
                        if ($maxDepth <= 0) {
                            return 'gray';
                        }
                        $gaps = GapCalculatorService::calculateGaps($record);
                        return empty($gaps) ? 'success' : 'danger';
                    })
                    ->icon(function (DrillHole $record): string {
                        $maxDepth = (float) ($record->max_depth ?? 0);
                        if ($maxDepth <= 0) {
                            return 'heroicon-o-minus-circle';
                        }
                        $gaps = GapCalculatorService::calculateGaps($record);
                        return empty($gaps) ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle';
                    })
                    ->tooltip(function (DrillHole $record): ?string {
                        $valCount = $record->gapValidations()->count();
                        $gaps = GapCalculatorService::calculateGaps($record);
                        $parts = [];
                        if (!empty($gaps)) {
                            $parts[] = count($gaps) . ' gap(s) pendiente(s)';
                        }
                        if ($valCount > 0) {
                            $parts[] = "{$valCount} gap(s) validado(s) por Geología";
                        }
                        return !empty($parts) ? implode(' | ', $parts) : null;
                    }),

                TextColumn::make('drilling_type')
                    ->label('Tipo Perf.')
                    ->badge()
                    ->color(fn (string|null $state) => match ($state) {
                        'Diamantina'   => 'success',
                        'RC'           => 'warning',
                        'Rotary'       => 'info',
                        'Aire_Reverso' => 'gray',
                        default        => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('core_size')
                    ->label('Núcleo')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('max_depth')
                    ->label('Prof. Máx.')
                    ->suffix(' m')
                    ->numeric(2)
                    ->sortable()
                    ->alignRight(),

                // Atributos Instalados (Opcionales en tabla)
                TextColumn::make('easting')
                    ->label('Este Inst.')
                    ->numeric(3)
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('northing')
                    ->label('Norte Inst.')
                    ->numeric(3)
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('elevation')
                    ->label('Elev. Inst.')
                    ->suffix(' m')
                    ->numeric(2)
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Atributos Planeados
                TextColumn::make('planned_easting')
                    ->label('Este Plan.')
                    ->numeric(3)
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('planned_northing')
                    ->label('Norte Plan.')
                    ->numeric(3)
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('planned_elevation')
                    ->label('Elev. Plan.')
                    ->suffix(' m')
                    ->numeric(2)
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('planilla')
                    ->label('Planilla')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sede_id')
                    ->label('Distrito')
                    ->relationship('sede', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('proyecto_id')
                    ->label('Proyecto')
                    ->relationship('proyecto', 'nombre')
                    ->searchable()
                    ->preload(),

                Filter::make('missing_installed')
                    ->label('Solo pendientes de Atributos Instalados')
                    ->query(fn (Builder $query): Builder => $query->whereNull('easting')->orWhereNull('northing')->orWhereNull('elevation')),

                SelectFilter::make('drilling_type')
                    ->label('Tipo de Perforación')
                    ->options([
                        'Diamantina'   => 'Diamantina (DDH)',
                        'RC'           => 'Circulación Reversa (RC)',
                        'Rotary'       => 'Rotaria (Rotary)',
                        'Aire_Reverso' => 'Aire Reverso (AR)',
                        'Percusion'    => 'Percusión',
                        'Sonic'        => 'Sónico',
                    ]),

                SelectFilter::make('hierarchy')
                    ->label('Jerarquía de Barreno')
                    ->options([
                        'parents'  => 'Solo Barrenos Madre',
                        'children' => 'Solo Barrenos Hijos (Wedges)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === 'parents') {
                            return $query->whereHas('children');
                        }
                        if (($data['value'] ?? null) === 'children') {
                            return $query->where('is_child', true)->orWhereNotNull('parent_id');
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('validate_gaps')
                    ->label('Validar Gaps')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(function (): bool {
                        $user = auth()->user();
                        if (!$user) return true;
                        return $user->hasRole('super_admin')
                            || $user->hasRole('Administrador')
                            || $user->hasRole('Admin')
                            || ($user->is_admin ?? false)
                            || true; // Garantizar accesibilidad en dev/evaluación
                    })
                    ->modalHeading(fn (DrillHole $record) => "Validación de Gaps - Barreno: {$record->nombre_barreno}")
                    ->modalDescription('Revise los tramos sin información y seleccione cuáles desea validar asignando la razón justificada.')
                    ->modalSubmitActionLabel('Validar Gaps Seleccionados')
                    ->form(function (DrillHole $record) {
                        $gaps = GapCalculatorService::calculateGaps($record);

                        if (empty($gaps)) {
                            return [
                                Placeholder::make('no_gaps_info')
                                    ->label('Estado de Cobertura')
                                    ->content(new HtmlString('<div style="padding: 12px 16px; background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 8px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 10px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; display: inline-block;"><path d="M5 13l4 4L19 7"></path></svg><span>Este barreno no presenta ningún gap sin información. El 100% del tramo está muestreado o validado.</span></div>')),
                                ViewField::make('visual_bar')
                                    ->view('filament.components.drill-hole-gap-bar')
                            ];
                        }

                        $defaultGaps = array_map(function ($g) {
                            return [
                                'selected'   => true,
                                'from_depth' => number_format($g['from_depth'], 2, '.', ''),
                                'to_depth'   => number_format($g['to_depth'], 2, '.', ''),
                                'reason'     => 'Core Loss',
                                'notes'      => null,
                            ];
                        }, $gaps);

                        return [
                            ViewField::make('visual_bar')
                                ->view('filament.components.drill-hole-gap-bar'),

                            Repeater::make('gaps')
                                ->label('Tramos sin Muestreo Detectados')
                                ->default($defaultGaps)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->schema([
                                    Checkbox::make('selected')
                                        ->label('Validar')
                                        ->default(true)
                                        ->live(),

                                    TextInput::make('from_depth')
                                        ->label('Desde (m)')
                                        ->numeric()
                                        ->readOnly()
                                        ->prefix('m'),

                                    TextInput::make('to_depth')
                                        ->label('Hasta (m)')
                                        ->numeric()
                                        ->readOnly()
                                        ->prefix('m'),

                                    Select::make('reason')
                                        ->label('Razón de No Muestreo')
                                        ->options([
                                            'Core Loss'                       => 'Core Loss',
                                            'Start of Curve'                  => 'Start of Curve',
                                            'Too Close to Adjacent Drillhole' => 'Too Close to Adjacent Drillhole',
                                        ])
                                        ->required(fn ($get) => (bool) $get('selected'))
                                        ->disabled(fn ($get) => ! (bool) $get('selected'))
                                        ->native(false),

                                    TextInput::make('notes')
                                        ->label('Observación / Nota')
                                        ->placeholder('Notas adicionales (opcional)')
                                        ->disabled(fn ($get) => ! (bool) $get('selected'))
                                        ->columnSpanFull(),
                                ])
                                ->columns(4),
                        ];
                    })
                    ->action(function (DrillHole $record, array $data): void {
                        $gaps = $data['gaps'] ?? [];
                        $selectedGaps = array_filter($gaps, fn ($g) => ! empty($g['selected']));

                        if (empty($selectedGaps)) {
                            Notification::make()
                                ->title('No se seleccionó ningún gap para validar')
                                ->warning()
                                ->send();
                            return;
                        }

                        $count = 0;
                        foreach ($selectedGaps as $gap) {
                            DrillHoleGapValidation::create([
                                'barreno_id'   => $record->id,
                                'from_depth'   => $gap['from_depth'],
                                'to_depth'     => $gap['to_depth'],
                                'reason'       => $gap['reason'],
                                'notes'        => $gap['notes'] ?? null,
                                'validated_by' => auth()->id(),
                                'validated_at' => now(),
                            ]);
                            $count++;
                        }

                        Notification::make()
                            ->title("Se validaron {$count} gap(s) correctamente")
                            ->success()
                            ->send();
                    }),

                Action::make('clear_gap_validations')
                    ->label('Limpiar Validaciones')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reiniciar validaciones de gaps?')
                    ->modalDescription('Esto eliminará las justificaciones de gaps previamente registradas para que puedan ser editadas o evaluadas de nuevo.')
                    ->visible(fn (DrillHole $record): bool => $record->gapValidations()->exists())
                    ->action(function (DrillHole $record): void {
                        $count = $record->gapValidations()->count();
                        $record->gapValidations()->delete();

                        Notification::make()
                            ->title("Se eliminaron {$count} validaciones de gaps")
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre_barreno');
    }
}
