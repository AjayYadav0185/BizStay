<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Details')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('property_id')
                            ->relationship('property', 'name')
                            ->searchable()->preload()->required()->live(),
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->options(fn (Forms\Get $get) => Tenant::query()
                                ->where('property_id', $get('property_id'))
                                ->whereIn('status', ['active', 'notice_period'])
                                ->pluck('full_name', 'id')
                                ->all())
                            ->searchable()
                            ->hidden(fn (Forms\Get $get) => blank($get('property_id'))),
                        Forms\Components\Select::make('type')
                            ->options(PaymentType::class)
                            ->default(PaymentType::Rent->value)->required()->live(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()->prefix('₹')->required(),
                        Forms\Components\DatePicker::make('period_month')
                            ->label('Rent Period (Month)')
                            ->format('Y-m')
                            ->displayFormat('MM/Y')
                            ->default(now()->startOfMonth())
                            ->visible(fn (Forms\Get $get) => $get('type') === PaymentType::Rent->value),
                        Forms\Components\DatePicker::make('due_date'),
                        Forms\Components\Select::make('status')
                            ->options(PaymentStatus::class)
                            ->default(PaymentStatus::Pending->value)->required()->live(),
                        Forms\Components\Select::make('method')
                            ->options(PaymentMethod::class)
                            ->label('Payment Method')
                            ->visible(fn (Forms\Get $get) => filled($get('status')) && $get('status') !== PaymentStatus::Pending->value),
                        Forms\Components\DatePicker::make('paid_at')
                            ->label('Paid On')
                            ->default(now())
                            ->visible(fn (Forms\Get $get) => filled($get('status')) && $get('status') === PaymentStatus::Paid->value),
                        Forms\Components\TextInput::make('transaction_ref')
                            ->label('Transaction Ref (UTR)')
                            ->visible(fn (Forms\Get $get) => filled($get('status')) && $get('status') === PaymentStatus::Paid->value),
                        Forms\Components\Textarea::make('notes')->columnSpan(2)->rows(2),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.full_name')
                    ->label('Tenant')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('property.name')->label('Property')->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('INR')->sortable()->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('period_month')
                    ->label('For Month')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M Y') : '—'),
                Tables\Columns\TextColumn::make('due_date')->date('d M Y')->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')->date('d M Y')->label('Paid On')->placeholder('—'),
                Tables\Columns\TextColumn::make('method')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('property')->relationship('property', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(PaymentStatus::class),
                Tables\Filters\SelectFilter::make('type')->options(PaymentType::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->status !== PaymentStatus::Paid)
                    ->form([
                        Forms\Components\Select::make('method')->options(PaymentMethod::class)->required(),
                        Forms\Components\DatePicker::make('paid_at')->default(now())->required(),
                        Forms\Components\TextInput::make('transaction_ref')->label('UTR / Ref No.'),
                    ])
                    ->action(function (array $data, Payment $record) {
                        $record->update([
                            'status' => PaymentStatus::Paid,
                            'method' => $data['method'],
                            'paid_at' => $data['paid_at'],
                            'transaction_ref' => $data['transaction_ref'] ?? null,
                            'collected_by' => auth()->id(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePayments::route('/'),
        ];
    }
}
