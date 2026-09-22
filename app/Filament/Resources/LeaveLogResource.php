<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\LeaveStatus;
use App\Filament\Resources\LeaveLogResource\Pages;
use App\Models\LeaveLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveLogResource extends Resource
{
    protected static ?string $model = LeaveLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Guests';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Leave')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('booking_id')->relationship('booking', 'id')->getOptionLabelFromRecordUsing(fn ($r): string => $r->guest?->full_name.' · Bed '.$r->bed?->bed_code)->searchable()->preload()->required(),
                    Forms\Components\DatePicker::make('start_date')->required()->native(false),
                    Forms\Components\DatePicker::make('end_date')->required()->native(false),
                    Forms\Components\Toggle::make('food_opt_out')->label('Food opt-out'),
                    Forms\Components\Select::make('status')->options(LeaveStatus::options())->default('requested')->required(),
                    Forms\Components\TextInput::make('reason')->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.guest.full_name')->label('Guest')->searchable(),
                Tables\Columns\TextColumn::make('start_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date('d M Y'),
                Tables\Columns\TextColumn::make('total_days')->label('Days'),
                Tables\Columns\IconColumn::make('food_opt_out')->boolean()->label('Food out'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(LeaveStatus::options())])
            ->actions([
                Tables\Actions\Action::make('approve')->color('success')->icon('heroicon-o-check')->visible(fn (LeaveLog $r): bool => $r->status === LeaveStatus::Requested)->action(fn (LeaveLog $r): bool => $r->approve(auth()->id())),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLeaveLogs::route('/'), 'create' => Pages\CreateLeaveLog::route('/create'), 'edit' => Pages\EditLeaveLog::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['booking.guest']);
    }
}
