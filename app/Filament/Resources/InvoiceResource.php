<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-rupee';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Invoice')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('invoice_number')->disabled()->dehydrated(false),
                    Forms\Components\Select::make('booking_id')->relationship('booking', 'id')->disabled()->dehydrated(false),
                    Forms\Components\Select::make('status')->options(InvoiceStatus::options())->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('rent_amount')->numeric()->prefix('₹'),
                    Forms\Components\TextInput::make('utility_amount')->numeric()->prefix('₹'),
                    Forms\Components\TextInput::make('maintenance_charges')->numeric()->prefix('₹'),
                    Forms\Components\TextInput::make('food_deduction')->numeric()->prefix('₹'),
                    Forms\Components\TextInput::make('other_charges')->numeric()->prefix('₹'),
                    Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('booking.guest.full_name')->label('Guest')->searchable()->description(fn (Invoice $r): string => 'Bed '.($r->booking?->bed?->bed_code ?? '—')),
                Tables\Columns\TextColumn::make('billing_cycle_start')->label('Cycle')->formatStateUsing(fn (Invoice $r): string => $r->billing_cycle_start->format('M Y')),
                Tables\Columns\TextColumn::make('total_due')->money('INR')->label('Billed'),
                Tables\Columns\TextColumn::make('balance_due')->label('Balance')->money('INR')->state(fn (Invoice $r): float => $r->balanceDue()),
                Tables\Columns\TextColumn::make('due_date')->date('d M Y')->color(fn (Invoice $r): string => $r->isOverdue() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(InvoiceStatus::options()),
                Tables\Filters\Filter::make('outstanding')->label('Outstanding only')->query(fn (Builder $q): Builder => $q->outstanding())->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Invoice $record): string => route('invoices.print', ['invoice' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('remind')
                    ->label('Remind')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->visible(fn (Invoice $r): bool => $r->isOverdue() && $r->balanceDue() > 0)
                    ->action(function (Invoice $record): void {
                        app(InvoiceService::class)->markReminded($record, 'manual');

                        Notification::make()
                            ->title('Reminder logged')
                            ->body($record->invoice_number.' — '.($record->booking?->guest?->full_name ?? 'guest').' reminded of ₹'.number_format($record->balanceDue()).' outstanding.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('collect')->label('Collect')->icon('heroicon-o-banknotes')->color('success')->visible(fn (Invoice $r): bool => $r->balanceDue() > 0)->form([Forms\Components\TextInput::make('amount')->numeric()->prefix('₹')->required()->default(fn (Invoice $r): float => $r->balanceDue()), Forms\Components\Select::make('payment_method')->options(PaymentMethod::collectionOptions())->default('upi')->required(), Forms\Components\TextInput::make('transaction_id')->label('UTR'), Forms\Components\DatePicker::make('paid_on')->default(now())->required()->native(false)])->action(function (Invoice $record, array $data): void {
                    app(InvoiceService::class)->recordPayment($record, $data);
                    Notification::make()->title('Payment recorded')->success()->send();
                }),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate')->label('Generate cycle')->icon('heroicon-o-document-plus')->requiresConfirmation()->action(function (): void {
                    $n = app(InvoiceService::class)->generateForCycle();
                    Notification::make()->title($n->count().' invoice(s)')->success()->send();
                }),
                Tables\Actions\Action::make('lateFees')
                    ->label('Apply late fees')
                    ->icon('heroicon-o-percent-badge')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Adds the property\'s late-fee percent to every overdue invoice as a visible line item. Each invoice is charged at most once.')
                    ->action(function (): void {
                        $result = app(InvoiceService::class)->applyLateFees();

                        Notification::make()
                            ->title($result['charged'].' invoice(s) charged')
                            ->body('₹'.number_format($result['amount']).' in late fees added to overdue invoices.')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('remindAll')
                    ->label('Remind all overdue')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Logs a reminder touchpoint for every overdue invoice not reminded in the last 3 days.')
                    ->action(function (): void {
                        $invoices = app(InvoiceService::class)->invoicesDueForReminder();

                        foreach ($invoices as $invoice) {
                            app(InvoiceService::class)->markReminded($invoice, 'bulk');
                        }

                        Notification::make()
                            ->title($invoices->count().' reminder(s) logged')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('due_date');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoices::route('/'), 'edit' => Pages\EditInvoice::route('/{record}/edit')];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['booking.guest', 'booking.bed']);
    }
}
