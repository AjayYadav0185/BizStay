<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Models\Guest;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Gate register: who came in, whom they met, and when they left.
 * Girls' PGs and most NCR societies require one; it is also the first thing
 * police ask for after an incident.
 */
class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Visitor Register';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('visitor_name')->required()->maxLength(255)->autofocus(),
                Forms\Components\TextInput::make('visitor_phone')->label('Mobile')->tel()->maxLength(20),
                Forms\Components\Select::make('guest_id')
                    ->label('Meeting resident')
                    ->options(fn (): array => Guest::query()->staying()->with('currentBooking.bed')->orderBy('full_name')->get()
                        ->mapWithKeys(fn (Guest $guest): array => [$guest->id => $guest->full_name.' · '.($guest->currentBooking?->bed?->bed_code ?? '—')])
                        ->all())
                    ->searchable()
                    ->placeholder('Office / delivery / none'),
                Forms\Components\Select::make('relation')
                    ->options(['Parent' => 'Parent', 'Sibling' => 'Sibling', 'Relative' => 'Relative', 'Friend' => 'Friend', 'Colleague' => 'Colleague', 'Delivery' => 'Delivery', 'Service / repair' => 'Service / repair', 'Prospective tenant' => 'Prospective tenant', 'Other' => 'Other']),
                Forms\Components\TextInput::make('purpose')->maxLength(255),
                Forms\Components\TextInput::make('id_proof')->label('ID seen (type + last 4)')->maxLength(60)->placeholder('Aadhaar XXXX 1234'),
                Forms\Components\DateTimePicker::make('checked_in_at')->label('In time')->default(now())->required()->seconds(false)->native(false),
                Forms\Components\DateTimePicker::make('checked_out_at')->label('Out time')->seconds(false)->native(false)->after('checked_in_at'),
                Forms\Components\Toggle::make('is_overnight')->label('Staying overnight (needs approval)'),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visitor_name')
                    ->label('Visitor')
                    ->searchable()
                    ->description(fn (Visitor $r): ?string => $r->visitor_phone),
                Tables\Columns\TextColumn::make('guest.full_name')->label('Meeting')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('relation')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('checked_in_at')->label('In')->dateTime('d M, h:i A')->sortable(),
                Tables\Columns\TextColumn::make('checked_out_at')
                    ->label('Out')
                    ->dateTime('d M, h:i A')
                    ->placeholder('Inside')
                    ->color(fn (Visitor $r): string => $r->isInside() ? 'warning' : 'gray'),
                Tables\Columns\IconColumn::make('is_overnight')->label('Overnight')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('loggedBy.name')->label('Logged by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('inside')->label('Still inside')->query(fn (Builder $query): Builder => $query->inside()),
                Tables\Filters\Filter::make('today')->label('Today')->query(fn (Builder $query): Builder => $query->whereDate('checked_in_at', now()->toDateString())),
                Tables\Filters\Filter::make('overnight')->label('Overnight')->query(fn (Builder $query): Builder => $query->where('is_overnight', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('checkOut')
                    ->label('Check out')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->visible(fn (Visitor $r): bool => $r->isInside())
                    ->action(function (Visitor $record): void {
                        $record->checkOut();
                        Notification::make()->title($record->visitor_name.' checked out')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('checkOutAll')
                    ->label('Check out selected')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->action(fn (Collection $records) => $records->each(fn (Visitor $visitor) => $visitor->checkOut()))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('checked_in_at', 'desc');
    }

    public static function getNavigationBadge(): ?string
    {
        $inside = Visitor::query()->inside()->count();

        return $inside > 0 ? (string) $inside : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Visitors currently inside';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['guest', 'loggedBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVisitors::route('/'),
        ];
    }
}
