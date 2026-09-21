<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BedStatus;
use App\Filament\Resources\BedResource\Pages;
use App\Models\Bed;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bed registry — every physical cot in the building with its live state.
 * Allocation itself happens through BedAllocationService (BookingResource),
 * so this grid is the inventory source of truth, not a booking form.
 */
class BedResource extends Resource
{
    protected static ?string $model = Bed::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Property';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'bed_code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bed')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('room_id')
                            ->relationship('room', 'room_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('bed_code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->helperText('e.g. 201-A'),
                        Forms\Components\TextInput::make('position')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(6)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options(BedStatus::options())
                            ->default(BedStatus::Available->value)
                            ->required(),
                        Forms\Components\TextInput::make('rent_override')
                            ->label('Rent override (₹)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Blank = charge the room base rent'),
                        Forms\Components\TextInput::make('maintenance_note')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bed_code')
                    ->label('Bed')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('room.floor_no')
                    ->label('Floor')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Ground' : 'Floor '.$state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('currentBooking.guest.full_name')
                    ->label('Occupant')
                    ->placeholder('—')
                    ->description(fn (Bed $record): string => $record->currentBooking?->check_in_date?->format('d M Y') ?? ''),
                Tables\Columns\TextColumn::make('effective_rent')
                    ->label('Rent')
                    ->money('INR')
                    ->state(fn (Bed $record): float => $record->effectiveRent())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('rent_override', $direction)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(BedStatus::options()),
                Tables\Filters\SelectFilter::make('room')
                    ->relationship('room', 'room_number')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('bed_code');
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeds::route('/'),
            'create' => Pages\CreateBed::route('/create'),
            'edit' => Pages\EditBed::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['room', 'currentBooking.guest']);
    }
}
