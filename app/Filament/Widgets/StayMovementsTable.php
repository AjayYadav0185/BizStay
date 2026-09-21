<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Arrivals, departures and notice-period watchlist for the next two weeks —
 * the "what needs to happen today" list.
 */
final class StayMovementsTable extends TableWidget
{
    protected static ?string $heading = 'Stay Movements (next 14 days)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->movementsQuery())
            ->columns([
                Tables\Columns\TextColumn::make('guest.full_name')
                    ->label('Guest')
                    ->searchable()
                    ->description(fn (Booking $record): string => $record->guest?->phone ?? '—'),
                Tables\Columns\TextColumn::make('bed.bed_code')
                    ->label('Bed')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('check_in_date')
                    ->label('Check-in')
                    ->date('d M Y')
                    ->color(fn (Booking $record): string => $record->check_in_date->isToday() ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('expected_check_out_date')
                    ->label('Expected Out')
                    ->date('d M Y')
                    ->placeholder('Open-ended')
                    ->color(fn (Booking $record): string => $record->expected_check_out_date?->isPast() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('outstanding_dues')
                    ->label('Dues')
                    ->money('INR')
                    ->state(fn (Booking $record): float => $record->outstandingDues())
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success'),
            ])
            ->recordUrl(fn (Booking $record): string => \App\Filament\Resources\BookingResource::getUrl('edit', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    /**
     * @return Builder<Booking>
     */
    private function movementsQuery(): Builder
    {
        $horizon = now()->addDays(14)->toDateString();

        return Booking::query()
            ->with(['guest', 'bed.room'])
            ->where(function (Builder $query) use ($horizon): void {
                $query
                    ->whereDate('check_in_date', '>=', now()->subDays(3)->toDateString())
                    ->orWhereDate('expected_check_out_date', '<=', $horizon)
                    ->orWhereDate('actual_check_out_date', '>=', now()->subDays(3)->toDateString())
                    ->orWhere('status', BookingStatus::NoticePeriod->value);
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [BookingStatus::NoticePeriod->value])
            ->orderBy('expected_check_out_date');
    }
}
