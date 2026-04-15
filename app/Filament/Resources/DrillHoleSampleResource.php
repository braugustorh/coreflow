<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrillHoleSampleResource\Pages;
use App\Models\DrillHoleSample;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DrillHoleSampleResource extends Resource
{
    protected static ?string $model = DrillHoleSample::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Drill Hole Samples';

    protected static ?string $modelLabel = 'Muestra';

    protected static ?string $pluralModelLabel = 'Muestras';


    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                // If they want to edit via form
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status_errors')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($record) => empty($record->errors) ? 'success' : 'danger')
                    ->state(fn($record) => empty($record->errors) ? 'OK' : 'Error')
                    ->description(fn($record) => empty($record->errors) ? null : implode(', ', $record->errors))
                    ->wrap(),

                Tables\Columns\TextInputColumn::make('bhid')->label('BHID'),
                Tables\Columns\TextInputColumn::make('from')->label('FROM')->type('number')->step(0.001),
                Tables\Columns\TextInputColumn::make('to')->label('TO')->type('number')->step(0.001),
                Tables\Columns\TextInputColumn::make('drilled_length')->label('DRILLED_LENGTH')->type('number')->step(0.001),
                Tables\Columns\TextInputColumn::make('sample_length')->label('SAMPLE_LENGTH')->type('number')->step(0.001),
                Tables\Columns\TextInputColumn::make('sample_number')->label('SAMPLE_NUMBER'),
                Tables\Columns\TextInputColumn::make('sample_type')->label('SAMPLE_TYPE'),
                Tables\Columns\TextInputColumn::make('control_type')->label('CONTROL_TYPE'),
                Tables\Columns\TextInputColumn::make('wght')->label('WGHT')->type('number')->step(0.001),
                Tables\Columns\TextInputColumn::make('comments')->label('COMMENTS'),

                Tables\Columns\TextInputColumn::make('project')->label('PROJECT'),
                Tables\Columns\TextInputColumn::make('core_size')->label('CORE SIZE'),
                Tables\Columns\TextInputColumn::make('work_order')->label('WORK ORDER'),
                Tables\Columns\TextInputColumn::make('costal')->label('COSTAL'),
            ])
            ->filters([
                //
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('Mass Assign')
                        ->label('Asignación Masiva')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Forms\Components\TextInput::make('project')->label('PROJECT'),
                            Forms\Components\TextInput::make('core_size')->label('CORE SIZE'),
                            Forms\Components\TextInput::make('work_order')->label('WORK ORDER'),
                            Forms\Components\TextInput::make('costal')->label('COSTAL'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                if (!empty($data['project']))
                                    $record->project = $data['project'];
                                if (!empty($data['core_size']))
                                    $record->core_size = $data['core_size'];
                                if (!empty($data['work_order']))
                                    $record->work_order = $data['work_order'];
                                if (!empty($data['costal']))
                                    $record->costal = $data['costal'];
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft')->where('user_id', auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrillHoleSamples::route('/'),
        ];
    }
}
