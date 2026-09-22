<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MeterReadingResource\Pages;
use App\Models\MeterReading;
use App\Models\UtilityMeter;
use App\Services\MeterReadingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeterReadingResource extends Resource
{
    protected static ?string $model = MeterReading::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Property';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Reading')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('meter_id')->relationship('meter', 'meter_serial_no')->getOptionLabelFromRecordUsing(fn ($r): string => $r->display_label)->searchable()->preload()->required(),
                    Forms\Components\DatePicker::make('reading_date')->required()->native(false)->default(now()),
                    Forms\Components\TextInput::make('current_reading')->numeric()->required()->minValue(0),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('meter.display_label')->label('Meter')->searchable(),
                Tables\Columns\TextColumn::make('reading_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('current_reading')->numeric(),
                Tables\Columns\TextColumn::make('consumption')->numeric(),
                Tables\Columns\TextColumn::make('amount')->money('INR'),
                Tables\Columns\IconColumn::make('anomaly')
                    ->label('Anomaly')
                    ->boolean()
                    ->state(fn (MeterReading $r): bool => $r->isAnomalous())
                    ->tooltip('Consumption > 2× this meter\'s average'),
                Tables\Columns\TextColumn::make('invoice.invoice_number')->label('Invoice')->placeholder('Unbilled')->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unbilled')->label('Unbilled only')->query(fn (Builder $query): Builder => $query->unbilled()),
            ])
            ->actions([
                Tables\Actions\Action::make('bulkEntry')
                    ->label('Bulk floor entry')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->modalDescription('Pick a floor and type each dial in one pass. Blank rows are skipped; an existing reading for the same meter + date is corrected, never duplicated.')
                    ->form([
                        Forms\Components\Select::make('floor')
                            ->label('Floor')
                            ->options(fn (): array => app(MeterReadingService::class)->floorsWithMeters())
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('reading_date')->default(now())->required()->native(false),
                        Forms\Components\Grid::make(2)
                            ->schema(fn (Forms\Get $get): array => app(MeterReadingService::class)
                                ->metersOnFloor((int) $get('floor'))
                                ->map(fn (UtilityMeter $meter): Forms\Components\TextInput => Forms\Components\TextInput::make('meter_'.$meter->id)
                                    ->label($meter->display_label)
                                    ->numeric()
                                    ->minValue(0)
                                    ->hint('prev: '.rtrim(rtrim((string) $meter->latestReadingValue(), '0'), '.').' · ₹'.$meter->resolveRate().'/unit')
                                    ->helperText($meter->latestReading ? 'Last: '.$meter->latestReading->reading_date->format('d M').' → '.$meter->latestReading->current_reading : 'No previous reading'))
                                ->all()),
                    ])
                    ->modalWidth('3xl')
                    ->modalSubmitActionLabel('Save readings')
                    ->action(function (array $data): void {
                        $rows = [];

                        foreach ($data as $key => $value) {
                            if (str_starts_with((string) $key, 'meter_') && filled($value)) {
                                $rows[] = ['meter_id' => (int) substr((string) $key, 6), 'current_reading' => $value];
                            }
                        }

                        if ($rows === []) {
                            Notification::make()->title('Nothing to save')->warning()->body('Enter at least one reading.')->send();

                            return;
                        }

                        $readings = app(MeterReadingService::class)->recordBulk($rows, $data['reading_date']);

                        Notification::make()
                            ->title($readings->count().' reading(s) recorded')
                            ->body('Consumption and amounts were computed and locked server-side.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('reading_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMeterReadings::route('/'), 'create' => Pages\CreateMeterReading::route('/create'), 'edit' => Pages\EditMeterReading::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['meter.room', 'invoice']);
    }
}
