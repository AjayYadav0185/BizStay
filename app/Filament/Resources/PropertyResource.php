<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\PropertyType;
use App\Filament\Resources\PropertyResource\Pages;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The building profile — a singleton. Everything the billing engine needs
 * (cycle anchor day, tariffs, meal rate, deposit policy, notice period) is
 * configured here and nowhere else.
 */
class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Property';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Building Profile';

    protected static ?string $pluralModelLabel = 'Building Profile';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identity')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. BizStay Homes — Sector 44'),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true)
                            ->placeholder('BSH-44'),
                        Forms\Components\Select::make('type')
                            ->options(PropertyType::class)
                            ->default(PropertyType::CoLive->value)
                            ->required(),
                        Forms\Components\TextInput::make('total_floors')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('manager_name'),
                        Forms\Components\TextInput::make('contact_phone')->tel(),
                    ]),
                ]),

                Forms\Components\Section::make('Location')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('address')->required()->rows(2)->columnSpanFull(),
                        Forms\Components\TextInput::make('locality'),
                        Forms\Components\TextInput::make('city')->required()->default('Gurgaon'),
                        Forms\Components\TextInput::make('state')->required()->default('Haryana'),
                        Forms\Components\TextInput::make('pincode')->numeric()->length(6),
                    ]),
                ]),

                Forms\Components\Section::make('Billing & tariffs')
                    ->description('Drives proration, utility billing and mess deductions. Review these before generating a cycle.')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('billing_cycle_start_day')
                                ->label('Cycle start day')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(28)
                                ->default(1)
                                ->required()
                                ->helperText('Day of the month invoices are raised'),
                            Forms\Components\TextInput::make('notice_period_days')
                                ->label('Notice period (days)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(90)
                                ->default(30)
                                ->required(),
                            Forms\Components\TextInput::make('security_deposit_months')
                                ->label('Deposit (months of rent)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(6)
                                ->default(1)
                                ->required(),
                            Forms\Components\TextInput::make('electricity_rate_per_unit')
                                ->label('Electricity rate')
                                ->numeric()
                                ->prefix('₹')
                                ->suffix('/ kWh')
                                ->default(9)
                                ->required(),
                            Forms\Components\TextInput::make('water_rate_per_unit')
                                ->label('Water rate')
                                ->numeric()
                                ->prefix('₹')
                                ->suffix('/ kL')
                                ->default(25)
                                ->required(),
                            Forms\Components\TextInput::make('meal_charge_per_day')
                                ->label('Mess charge')
                                ->numeric()
                                ->prefix('₹')
                                ->suffix('/ day')
                                ->default(120)
                                ->required()
                                ->helperText('Credited back for approved food opt-outs'),
                            Forms\Components\TextInput::make('maintenance_charge_per_bed')
                                ->label('Maintenance per bed')
                                ->numeric()
                                ->prefix('₹')
                                ->suffix('/ month')
                                ->default(0),
                            Forms\Components\TextInput::make('late_fee_percent')
                                ->label('Late fee')
                                ->numeric()
                                ->suffix('%')
                                ->default(0),
                        ]),
                    ]),

                Forms\Components\Section::make('Amenities & status')->schema([
                    Forms\Components\CheckboxList::make('amenities')
                        ->columns(3)
                        ->columnSpanFull()
                        ->options([
                            'High-Speed WiFi' => 'High-Speed WiFi',
                            'AC Rooms' => 'AC Rooms',
                            'Power Backup' => 'Power Backup',
                            'Meals Included' => 'Meals Included',
                            'Laundry' => 'Laundry',
                            'CCTV Security' => 'CCTV Security',
                            'Housekeeping' => 'Housekeeping',
                            'RO Water' => 'RO Water',
                            'Common TV' => 'Common TV',
                        ]),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('total_floors')->label('Floors')->numeric(),
                Tables\Columns\TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->state(fn (Property $record): string => $record->occupancyPercent().'%'),
                Tables\Columns\TextColumn::make('electricity_rate_per_unit')->label('₹/kWh')->toggleable(),
                Tables\Columns\TextColumn::make('meal_charge_per_day')->label('Mess ₹/day')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    /**
     * BizStay runs one building: a second profile would silently split the
     * ledger, so creation is blocked once a profile exists.
     */
    public static function canCreate(): bool
    {
        return Property::query()->doesntExist();
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
