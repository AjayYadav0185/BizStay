<?php

namespace App\Filament\Resources;

use App\Enums\RoomStatus;
use App\Filament\Resources\RoomResource\Pages;
use App\Filament\Resources\RoomResource\RelationManagers\BedsRelationManager;
use App\Models\Property;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('property_id')
                            ->relationship('property', 'name')
                            ->searchable()->preload()->required(),
                        Forms\Components\TextInput::make('room_number')
                            ->required()->maxLength(20)
                            ->placeholder('e.g. 201'),
                        Forms\Components\TextInput::make('floor')
                            ->numeric()->minValue(0)->default(0)->required(),
                        Forms\Components\Select::make('sharing_capacity')
                            ->options([1 => '1 (Single)', 2 => '2 (Double)', 3 => '3 (Triple)', 4 => '4 (Quad)'])
                            ->default(2)->required()
                            ->label('Sharing'),
                        Forms\Components\TextInput::make('monthly_rent')
                            ->numeric()->prefix('₹')->required(),
                        Forms\Components\TextInput::make('security_deposit')
                            ->numeric()->prefix('₹'),
                        Forms\Components\Toggle::make('has_ac')->label('AC'),
                        Forms\Components\Toggle::make('attached_bathroom')->default(true)->label('Attached Bathroom'),
                        Forms\Components\Select::make('status')
                            ->options(RoomStatus::class)
                            ->default(RoomStatus::Available->value)->required(),
                        Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('property.name')
                    ->label('Property')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('room_number')
                    ->badge()->color('primary')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('floor')
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'Ground' : 'Floor '.$state),
                Tables\Columns\TextColumn::make('sharing_capacity')
                    ->label('Sharing')->formatStateUsing(fn ($state) => $state.' Sharing'),
                Tables\Columns\TextColumn::make('monthly_rent')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('beds_count')->counts('beds')->label('Beds'),
                Tables\Columns\TextColumn::make('occupied_beds')
                    ->label('Occupied')
                    ->state(fn (Room $record) => $record->beds->where('status', 'occupied')->count()),
                Tables\Columns\IconColumn::make('has_ac')->boolean()->label('AC'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('property')
                    ->relationship('property', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(RoomStatus::class),
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

    public static function getRelations(): array
    {
        return [
            BedsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
