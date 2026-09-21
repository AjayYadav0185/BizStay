<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Outstanding money, oldest first, with a one-click collection that writes a
 * ledger row (never a raw balance edit).
 */
final class DueInvoicesTable extends TableWidget
{
    protected static ?string $heading = 'Outstanding Dues (collect now)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->outstanding()
                    ->with(['booking.guest', 'booking.bed.room'])
                    ->orderBy('due_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('booking.guest.full_name')
                    ->label('Guest')
                    ->searchable()
                    ->description(fn (Invoice $record): string => 'Bed '.($record->booking?->bed?->bed_code ?? '—')),
                Tables\Columns\TextColumn::make('billing_cycle_start')
                    ->label('Cycle')
                    ->formatStateUsing(fn (Invoice $record): string => $record->billing_cycle_start->format('M Y')),
                Tables\Columns\TextColumn::make('total_due')->money('INR')->label('Billed'),
                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance')
                    ->money('INR')
                    ->state(fn (Invoice $record): float => $record->balanceDue())
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d M Y')
                    ->color(fn (Invoice $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('collect')
                    ->label('Collect')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->balanceDue() > 0)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->default(fn (Invoice $record): float => $record->balanceDue())
                            ->maxValue(fn (Invoice $record): float => $record->balanceDue()),
                        Forms\Components\Select::make('payment_method')
                            ->options(PaymentMethod::collectionOptions())
                            ->default(PaymentMethod::Upi->value)
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction Ref (UTR)')
                            ->required(fn (Forms\Get $get): bool => PaymentMethod::tryFrom((string) $get('payment_method'))?->requiresTransactionId() ?? false),
                        Forms\Components\DatePicker::make('paid_on')->default(now())->required(),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        // InvoiceService + PaymentObserver settle the balance and
                        // flip the invoice status; no status is written here.
                        app(InvoiceService::class)->recordPayment($record, $data);

                        Notification::make()
                            ->title('Payment recorded')
                            ->body('₹'.number_format((float) $data['amount']).' collected for '.$record->invoice_number.'.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate')
                    ->label('Generate this cycle')
                    ->icon('heroicon-o-document-plus')
                    ->requiresConfirmation()
                    ->modalDescription('Raises invoices for every live stay that has not been billed for the current cycle. Safe to re-run: existing invoices are refreshed, never duplicated.')
                    ->action(function (): void {
                        $invoices = app(InvoiceService::class)->generateForCycle();

                        Notification::make()
                            ->title($invoices->count().' invoice(s) generated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('markOverdue')
                    ->label('Flag overdue')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->action(function (): void {
                        $count = app(InvoiceService::class)->markOverdue();

                        Notification::make()
                            ->title($count.' invoice(s) flagged overdue')
                            ->warning()
                            ->send();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        InvoiceStatus::Unpaid->value => 'Unpaid',
                        InvoiceStatus::PartiallyPaid->value => 'Partially Paid',
                        InvoiceStatus::Overdue->value => 'Overdue',
                    ]),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
