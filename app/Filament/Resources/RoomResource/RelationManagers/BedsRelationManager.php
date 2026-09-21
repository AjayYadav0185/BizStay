<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomResource\RelationManagers;

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Services\BedAllocationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Bed-level operations inside a room: block a cot for repairs, release it, or
 * record a premium/discount rent for a specific bed.
 */
class BedsRelationManager extends RelationManager
{
    protected static string $relationship = 'beds';

    protected static ?string $title = 'Beds';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('bed_code')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->helperText('e.g. 201-C'),
                    Forms\Components\TextInput::make('position')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(6)
                        ->default(1)
                        ->required(),
                    Forms\Components\TextInput::make('rent_override')
                        ->label('Rent override')
                        ->numeric()
                        ->prefix('₹')
                        ->helperText('Leave blank to charge the room base rent'),
                    Forms\Components\Select::make('status')
                        ->options(BedStatus::options())
                        ->default(BedStatus::Available->value)
                        ->required(),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('bed_code')
            ->columns([
                Tables\Columns\TextColumn::make('bed_code')
                    ->label('Bed')
                    ->badge()
                    ->color(fn (string $state): string => 'gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('effective_rent')
                    ->label('Rent')
                    ->money('INR')
                    ->state(fn (Bed $record): float => $record->effectiveRent()),
                Tables\Columns\TextColumn::make('currentBooking.guest.full_name')
                    ->label('Occupied by')
                    ->placeholder('—')
                    ->description(fn (Bed $record): string => $record->currentBooking?->check_in_date?->format('d M Y') ?? ''),
                Tables\Columns\TextColumn::make('maintenance_note')
                    ->label('Maintenance')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(BedStatus::options()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('maintenance')
                    ->label('Take offline')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('danger')
                    ->visible(fn (Bed $record): bool => $record->status !== BedStatus::Maintenance)
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('What is being fixed?')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (Bed $record, array $data): void {
                        app(BedAllocationService::class)->takeBedOffline($record, $data['note']);

                        Notification::make()->title('Bed moved to maintenance')->warning()->send();
                    }),
                Tables\Actions\Action::make('back_online')
                    ->label('Back online')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Bed $record): bool => $record->status === BedStatus::Maintenance)
                    ->requiresConfirmation()
                    ->action(function (Bed $record): void {
                        app(BedAllocationService::class)->bringBedOnline($record);

                        Notification::make()->title('Bed is available again')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('position');
    }
}
