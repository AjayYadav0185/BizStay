<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MeterReadingResource\Pages;
use App\Models\MeterReading;
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
                Tables\Columns\TextColumn::make('invoice.invoice_number')->label('Invoice')->placeholder('Unbilled')->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unbilled')->label('Unbilled only')->query(fn (Builder $q): Builder => $q->unbilled()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
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
