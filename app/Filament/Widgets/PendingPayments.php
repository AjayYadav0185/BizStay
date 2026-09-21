<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class PendingPayments extends TableWidget
{
    protected static ?string $heading = 'Rent Due (Pending & Overdue)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->whereIn('status', ['pending', 'overdue'])
                    ->orderBy('due_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('tenant.full_name')->label('Tenant')->placeholder('—'),
                Tables\Columns\TextColumn::make('property.name')->label('Property')->toggleable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('INR')->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('period_month')
                    ->label('For Month')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M Y') : '—'),
                Tables\Columns\TextColumn::make('due_date')
                    ->date('d M Y')
                    ->color(fn ($state) => $state && Carbon::parse($state)->isPast() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('collect')
                    ->label('Collect')
                    ->icon('heroicon-m-currency-rupee')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('method')
                            ->options(PaymentMethod::class)->required(),
                    ])
                    ->action(function (array $data, Payment $record) {
                        $record->update([
                            'status' => PaymentStatus::Paid,
                            'method' => $data['method'],
                            'paid_at' => now(),
                            'collected_by' => auth()->id(),
                        ]);
                    }),
            ])
            ->paginated(false);
    }
}
