<?php

namespace App\Filament\Resources;

use App\Enums\BedStatus;
use App\Filament\Resources\BedResource\Pages;
use App\Models\Bed;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BedResource extends Resource
{
    protected static ?string $model = Bed::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bed Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('room_id')
                            ->relationship('room', 'room_number')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->property->name.' — Room '.$record->room_number)
                            ->searchable()->preload()->required(),
                        Forms\Components\TextInput::make('bed_number')
                            ->required()->maxLength(5)
                            ->placeholder('e.g. A'),
                        Forms\Components\Select::make('status')
                            ->options(BedStatus::class)
                            ->default(BedStatus::Vacant->value)->required(),
                        Forms\Components\TextInput::make('monthly_rent')
                            ->numeric()->prefix('₹')
                            ->helperText('Leave empty to use the room rent'),
                        Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.property.name')
                    ->label('Property')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room')->badge()->color('primary')->sortable(),
                Tables\Columns\TextColumn::make('bed_number')
                    ->label('Bed')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('effective_rent')
                    ->label('Rent')->money('INR'),
                Tables\Columns\TextColumn::make('tenant.full_name')
                    ->label('Occupied By')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(BedStatus::class),
                Tables\Filters\SelectFilter::make('property')
                    ->relationship('room.property', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBeds::route('/'),
        ];
    }
}
