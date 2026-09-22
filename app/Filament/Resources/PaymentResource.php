<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Payment')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('invoice_id')->relationship('invoice', 'invoice_number')->searchable()->preload()->required(),
                    Forms\Components\Select::make('payment_type')->options(PaymentType::options())->default('rent')->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->prefix('₹')->required()->minValue(0),
                    Forms\Components\Select::make('payment_method')->options(PaymentMethod::options())->required(),
                    Forms\Components\TextInput::make('transaction_id')->label('UTR')->unique(ignoreRecord: true),
                    Forms\Components\Select::make('status')->options(PaymentStatus::options())->default('success')->required(),
                    Forms\Components\DatePicker::make('paid_on')->required()->native(false)->default(now()),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_on')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')->label('Invoice')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('booking.guest.full_name')->label('Guest')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('payment_type')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PaymentStatus::options()),
                Tables\Filters\SelectFilter::make('payment_method')->options(PaymentMethod::options()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('paid_on', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/'), 'edit' => Pages\EditPayment::route('/{record}/edit')];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['invoice', 'booking.guest']);
    }
}
