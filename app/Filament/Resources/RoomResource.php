<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RoomStatus;
use App\Enums\SharingType;
use App\Filament\Resources\RoomResource\Pages;
use App\Filament\Resources\RoomResource\RelationManagers\BedsRelationManager;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The interactive room & bed grid: one row per room with an instant visual
 * badge stack of the beds inside it.
 */
class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Property';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'room_number';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room layout')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('floor_no')
                            ->label('Floor')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(0)
                            ->required()
                            ->helperText('0 = ground floor'),
                        Forms\Components\TextInput::make('room_number')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. 201'),
                        Forms\Components\Select::make('sharing_type')
                            ->options(SharingType::options())
                            ->default(SharingType::Double->value)
                            ->required()
                            ->live()
                            ->helperText(fn (Forms\Get $get): string => 'Creates '.(
                                SharingType::tryFrom((string) $get('sharing_type'))?->capacity() ?? 0
                            ).' bed(s) on save'),
                    ]),
                ]),

                Forms\Components\Section::make('Commercials')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('base_rent_per_bed')
                            ->label('Base rent per bed')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->minValue(0)
                            ->helperText('Gurgaon PG: single ~₹18k · double ~₹10k · triple ~₹8k'),
                        Forms\Components\TextInput::make('nightly_rate')
                            ->label('Nightly rate (hotel)')
                            ->numeric()
                            ->prefix('₹')
                            ->suffix('/ night')
                            ->minValue(0)
                            ->helperText('Only for PG + Hotel properties. ≤₹7,500 → 12% GST, above → 18%'),
                        Forms\Components\TextInput::make('security_deposit_default')
                            ->label('Deposit (months)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(6)
                            ->default(1)
                            ->helperText('Multiplied by rent when allotting a bed'),
                        Forms\Components\Select::make('status')
                            ->options(RoomStatus::options())
                            ->default(RoomStatus::Available->value)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-derived from bed states (maintenance is manual)'),
                    ]),
                ]),

                Forms\Components\Section::make('Amenities')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('has_ac')->label('Air conditioning'),
                        Forms\Components\Toggle::make('attached_bathroom')->label('Attached bathroom')->default(true),
                        Forms\Components\Toggle::make('has_balcony')->label('Balcony'),
                    ]),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('floor_no')
                    ->label('Floor')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Ground' : 'Floor '.$state)
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('room_number')
                    ->label('Room')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sharing_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_rent_per_bed')
                    ->label('Rent / bed')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\ViewColumn::make('beds')
                    ->label('Beds & occupancy')
                    ->view('filament.tables.columns.bed-stack'),
                Tables\Columns\TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->state(fn (Room $record): string => $record->occupancyPercent().'%')
                    ->color(fn (Room $record): string => match (true) {
                        $record->occupancyPercent() >= 100 => 'primary',
                        $record->occupancyPercent() > 0 => 'warning',
                        default => 'success',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_ac')->boolean()->label('AC')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('floor_no')
                    ->label('Floor')
                    ->options(fn (): array => Room::query()
                        ->distinct()
                        ->orderBy('floor_no')
                        ->pluck('floor_no')
                        ->mapWithKeys(fn (int $floor): array => [$floor => $floor === 0 ? 'Ground' : 'Floor '.$floor])
                        ->all()),
                Tables\Filters\SelectFilter::make('sharing_type')->options(SharingType::options()),
                Tables\Filters\SelectFilter::make('status')->options(RoomStatus::options()),
                Tables\Filters\Filter::make('has_free_beds')
                    ->label('Has a free bed')
                    ->query(fn (Builder $query): Builder => $query->withFreeBeds()),
                Tables\Filters\TernaryFilter::make('has_ac')->label('Air conditioning'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('offline')
                    ->label('Take offline')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('danger')
                    ->visible(fn (Room $record): bool => $record->status !== RoomStatus::Maintenance)
                    ->requiresConfirmation()
                    ->action(function (Room $record): void {
                        $record->forceFill(['status' => RoomStatus::Maintenance])->save();
                    }),
                Tables\Actions\Action::make('online')
                    ->label('Bring online')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Room $record): bool => $record->status === RoomStatus::Maintenance)
                    ->requiresConfirmation()
                    ->action(function (Room $record): void {
                        $record->forceFill(['status' => RoomStatus::Available])->save();
                        $record->syncStatusFromBeds();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('room_number');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            BedsRelationManager::class,
        ];
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['beds.currentBooking.guest']);
    }
}
