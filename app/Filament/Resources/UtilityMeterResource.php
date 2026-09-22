<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\MeterType;
use App\Filament\Resources\UtilityMeterResource\Pages;
use App\Models\UtilityMeter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UtilityMeterResource extends Resource
{
    protected static ?string $model = UtilityMeter::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationGroup = 'Property';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Meter')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('room_id')->relationship('room', 'room_number')->searchable()->preload()->required(),
                    Forms\Components\Select::make('meter_type')->options(MeterType::options())->required(),
                    Forms\Components\TextInput::make('meter_serial_no')->required()->maxLength(60)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('multiplier')->numeric()->default(1)->required(),
                    Forms\Components\TextInput::make('rate_per_unit')->numeric()->prefix('₹')->helperText('Blank = property tariff'),
                    Forms\Components\TextInput::make('fixed_share_count')->numeric()->minValue(1),
                    Forms\Components\DatePicker::make('installed_on')->native(false),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.room_number')->label('Room')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('meter_type')->badge(),
                Tables\Columns\TextColumn::make('meter_serial_no')->label('Serial')->searchable(),
                Tables\Columns\TextColumn::make('multiplier'),
                Tables\Columns\TextColumn::make('rate_per_unit')->label('Rate')->money('INR')->placeholder('Property rate'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('meter_type')->options(MeterType::options())])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUtilityMeters::route('/'), 'create' => Pages\CreateUtilityMeter::route('/create'), 'edit' => Pages\EditUtilityMeter::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['room']);
    }
}
