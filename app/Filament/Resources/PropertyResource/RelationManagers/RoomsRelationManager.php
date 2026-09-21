<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Enums\RoomStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('room_number')->required()->maxLength(20),
                    Forms\Components\TextInput::make('floor')->numeric()->minValue(0)->default(0)->required(),
                    Forms\Components\Select::make('sharing_capacity')
                        ->options([1 => '1 (Single)', 2 => '2 (Double)', 3 => '3 (Triple)', 4 => '4 (Quad)'])
                        ->default(2)->required()->label('Sharing'),
                    Forms\Components\TextInput::make('monthly_rent')->numeric()->prefix('₹')->required(),
                    Forms\Components\TextInput::make('security_deposit')->numeric()->prefix('₹'),
                    Forms\Components\Toggle::make('has_ac')->label('AC'),
                    Forms\Components\Toggle::make('attached_bathroom')->default(true)->label('Attached Bathroom'),
                    Forms\Components\Select::make('status')
                        ->options(RoomStatus::class)
                        ->default(RoomStatus::Available->value)->required(),
                    Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('room_number')
            ->columns([
                Tables\Columns\TextColumn::make('room_number')
                    ->badge()->color('primary')->sortable(),
                Tables\Columns\TextColumn::make('floor')
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'Ground' : 'Floor '.$state),
                Tables\Columns\TextColumn::make('sharing_capacity')
                    ->label('Sharing')->formatStateUsing(fn ($state) => $state.' Sharing'),
                Tables\Columns\TextColumn::make('monthly_rent')->money('INR'),
                Tables\Columns\TextColumn::make('beds_count')->counts('beds')->label('Beds'),
                Tables\Columns\TextColumn::make('occupied_beds')
                    ->label('Occupied')
                    ->state(fn ($record) => $record->beds->where('status', 'occupied')->count()),
                Tables\Columns\IconColumn::make('has_ac')->boolean()->label('AC'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(RoomStatus::class),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
