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
                Forms\Components\TextInput::make('bhid')->label('BHID'),
                Forms\Components\TextInput::make('from')->label('FROM')->numeric()->step(0.001),
                Forms\Components\TextInput::make('to')->label('TO')->numeric()->step(0.001),
                Forms\Components\TextInput::make('drilled_length')->label('DRILLED_LENGTH')->numeric()->step(0.001),
                Forms\Components\TextInput::make('sample_length')->label('SAMPLE_LENGTH')->numeric()->step(0.001),
                Forms\Components\TextInput::make('sample_number')->label('SAMPLE_NUMBER'),
                Forms\Components\TextInput::make('sample_type')->label('SAMPLE_TYPE'),
                Forms\Components\TextInput::make('control_type')->label('CONTROL_TYPE'),
                Forms\Components\TextInput::make('wght')->label('WGHT')->numeric()->step(0.001),
                Forms\Components\TextInput::make('comments')->label('COMMENTS'),
                Forms\Components\TextInput::make('project')->label('PROJECT'),
                Forms\Components\TextInput::make('core_size')->label('CORE SIZE'),
                Forms\Components\TextInput::make('work_order')->label('WORK ORDER'),
                Forms\Components\TextInput::make('costal')->label('COSTAL'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                Tables\Columns\TextColumn::make('status_errors')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($record) => empty($record->errors) ? 'success' : 'danger')
                    ->state(fn($record) => empty($record->errors) ? 'OK' : 'Error')
                    ->description(fn($record) => empty($record->errors) ? null : implode(', ', $record->errors))
                    ->wrap(),

                Tables\Columns\TextColumn::make('bhid')->label('BHID')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('from')->label('FROM')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('to')->label('TO')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('drilled_length')->label('DRILLED_LENGTH')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('sample_length')->label('SAMPLE_LENGTH')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('sample_number')->label('SAMPLE_NUMBER')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sample_type')->label('SAMPLE_TYPE')->searchable(),
                Tables\Columns\TextColumn::make('control_type')->label('CONTROL_TYPE')->searchable(),
                Tables\Columns\TextColumn::make('wght')->label('WGHT')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('comments')->label('COMMENTS')->limit(50),

                Tables\Columns\TextColumn::make('project')->label('PROJECT')->searchable(),
                Tables\Columns\TextColumn::make('core_size')->label('CORE SIZE'),
                Tables\Columns\TextColumn::make('work_order')->label('WORK ORDER')->searchable(),
                Tables\Columns\TextColumn::make('costal')->label('COSTAL'),
            ])
            ->filters([
                Tables\Filters\Filter::make('con_errores')
                    ->label('Con Errores')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('errors')),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
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
