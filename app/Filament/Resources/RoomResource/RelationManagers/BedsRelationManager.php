<?php

namespace App\Filament\Resources\RoomResource\RelationManagers;

use App\Enums\BedStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BedsRelationManager extends RelationManager
{
    protected static string $relationship = 'beds';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('bed_number')
            ->columns([
                Tables\Columns\TextColumn::make('bed_number')
                    ->label('Bed')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('monthly_rent')
                    ->money('INR')
                    ->formatStateUsing(fn ($state, $record) => $state ? '₹'.number_format((float) $state) : '(room rate)'),
                Tables\Columns\TextColumn::make('tenant.full_name')
                    ->label('Occupied By')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(BedStatus::class),
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
