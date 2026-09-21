<?php

namespace App\Filament\Resources;

use App\Enums\PropertyType;
use App\Filament\Resources\PropertyResource\Pages;
use App\Filament\Resources\PropertyResource\RelationManagers\RoomsRelationManager;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'PG Property';

    protected static ?string $pluralModelLabel = 'PG Properties';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Property Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. BizStay Homes Sector 44'),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g. BSH-44'),
                        Forms\Components\Select::make('type')
                            ->options(PropertyType::class)
                            ->default(PropertyType::CoLive->value)
                            ->required(),
                        Forms\Components\TextInput::make('total_floors')
                            ->numeric()->minValue(1)->default(1)->required(),
                    ]),
                ]),
                Forms\Components\Section::make('Location (Gurgaon)')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('address')->required()->rows(2)->columnSpan(2),
                        Forms\Components\TextInput::make('locality')
                            ->placeholder('e.g. Sector 44 / Golf Course Road / DLF Phase 3'),
                        Forms\Components\TextInput::make('city')->default('Gurgaon')->required(),
                        Forms\Components\TextInput::make('state')->default('Haryana')->required(),
                        Forms\Components\TextInput::make('pincode')->numeric()->length(6),
                    ]),
                ]),
                Forms\Components\Section::make('Management')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('manager_name'),
                        Forms\Components\TextInput::make('contact_phone')->tel(),
                        Forms\Components\TextInput::make('security_deposit_months')
                            ->numeric()->minValue(0)->maxValue(6)->default(1)
                            ->label('Security Deposit (months)'),
                        Forms\Components\TextInput::make('notice_period_days')
                            ->numeric()->minValue(0)->maxValue(120)->default(30)
                            ->label('Notice Period (days)'),
                        Forms\Components\CheckboxList::make('amenities')
                            ->columnSpan(2)->columns(4)
                            ->options([
                                'High-Speed WiFi' => 'High-Speed WiFi',
                                'AC Rooms' => 'AC Rooms',
                                'Power Backup' => 'Power Backup',
                                'Meals Included' => 'Meals Included',
                                'Laundry' => 'Laundry',
                                'CCTV Security' => 'CCTV Security',
                                'Hot Water' => 'Hot Water',
                                'Housekeeping' => 'Housekeeping',
                                'Parking' => 'Parking',
                                'RO Water' => 'RO Water',
                                'Common TV' => 'Common TV',
                                'Reception' => 'Reception',
                            ]),
                        Forms\Components\Toggle::make('is_active')->default(true)->label('Active'),
                        Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    // __TABLE_MARKER__

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()->sortable()
                    ->description(fn (Property $record): string => $record->locality.', '.$record->city),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('rooms_count')->counts('rooms')->label('Rooms'),
                Tables\Columns\TextColumn::make('beds_count')->counts('beds')->label('Beds'),
                Tables\Columns\TextColumn::make('occupancy_percent')
                    ->label('Occupancy')->suffix('%')->badge()
                    ->color(fn (string $state): string => (float) $state >= 90 ? 'success' : ((float) $state >= 60 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('contact_phone')->label('Contact')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(PropertyType::class),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            RoomsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
