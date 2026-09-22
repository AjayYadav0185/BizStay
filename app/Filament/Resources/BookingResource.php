<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Bed;
use App\Models\Booking;
use App\Services\BedAllocationService;
use App\Services\CheckoutSettlementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Guests';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Stay')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('guest_id')->relationship('guest', 'full_name')->searchable()->preload()->required(),
                    Forms\Components\Select::make('bed_id')->label('Bed')->options(fn (): array => Bed::query()->with('room')->allocatable()->limit(200)->get()->mapWithKeys(fn (Bed $b): array => [$b->id => $b->bed_code.' · '.$b->room?->room_number]))->searchable()->required(),
                    Forms\Components\Select::make('status')->options(BookingStatus::options())->default('active')->required()->disabled()->dehydrated(),
                    Forms\Components\DatePicker::make('check_in_date')->required()->native(false)->default(now()),
                    Forms\Components\DatePicker::make('expected_check_out_date')->native(false),
                    Forms\Components\TextInput::make('monthly_rent')->numeric()->prefix('₹')->required(),
                    Forms\Components\TextInput::make('security_deposit_amount')->numeric()->prefix('₹')->default(0),
                    Forms\Components\TextInput::make('rent_due_day')->numeric()->minValue(1)->maxValue(28)->default(5),
                    Forms\Components\Toggle::make('food_included')->default(true),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guest.full_name')->label('Guest')->searchable()->description(fn (Booking $r): string => $r->guest?->phone ?? ''),
                Tables\Columns\TextColumn::make('bed.bed_code')->label('Bed')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('check_in_date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('monthly_rent')->money('INR')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(BookingStatus::options()),
                Tables\Filters\Filter::make('live')->label('Live only')->query(fn (Builder $q): Builder => $q->live())->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('notice')->label('Notice')->icon('heroicon-o-clock')->color('warning')->visible(fn (Booking $r): bool => $r->status === BookingStatus::Active)->requiresConfirmation()->action(fn (Booking $r) => app(BedAllocationService::class)->putOnNotice($r)),
                Tables\Actions\Action::make('withdraw')->label('Withdraw')->icon('heroicon-o-arrow-uturn-left')->visible(fn (Booking $r): bool => $r->status === BookingStatus::NoticePeriod)->requiresConfirmation()->action(fn (Booking $r) => app(BedAllocationService::class)->withdrawNotice($r)),
                Tables\Actions\Action::make('checkout')->label('Settle & out')->icon('heroicon-o-arrow-right-on-rectangle')->color('danger')->visible(fn (Booking $r): bool => $r->status->canCheckOut())->form([Forms\Components\DatePicker::make('checkout_date')->default(now())->required()->native(false), Forms\Components\TextInput::make('damages')->numeric()->default(0)->prefix('₹')])->action(function (Booking $record, array $data): void {
                    $date = Carbon::parse($data['checkout_date'] ?? now());
                    $result = app(CheckoutSettlementService::class)->checkout($record, $date, ['damages' => (float) ($data['damages'] ?? 0)]);
                    Notification::make()->title('Checked out')->body('Settlement '.$result['invoice']->invoice_number)->success()->send();
                }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('check_in_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBookings::route('/'), 'create' => Pages\CreateBooking::route('/create'), 'edit' => Pages\EditBooking::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['guest', 'bed.room']);
    }
}
