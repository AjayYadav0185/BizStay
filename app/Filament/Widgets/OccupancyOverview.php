<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Room;
use App\Services\InvoiceService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Visual occupancy dashboard: the single screen a manager opens in the morning.
 * Every number is derived live from the bed table, so the dashboard can never
 * disagree with the inventory.
 */
final class OccupancyOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $total = Bed::query()->count();
        $occupied = Bed::query()->withStatus(BedStatus::Occupied)->count();
        $available = Bed::query()->withStatus(BedStatus::Available)->count();
        $maintenance = Bed::query()->withStatus(BedStatus::Maintenance)->count();
        $reserved = Bed::query()->withStatus(BedStatus::Reserved)->count();

        $occupancy = $total > 0 ? round($occupied / $total * 100, 1) : 0.0;
        $roomCount = Room::query()->count();

        $expectedRent = app(InvoiceService::class)->expectedRentFor();

        $dues = round((float) Booking::query()->live()->get()
            ->sum(fn (Booking $booking): float => $booking->outstandingDues()), 2);

        return [
            Stat::make('Occupancy', $occupancy.'%')
                ->description("{$occupied} of {$total} beds occupied · {$roomCount} rooms")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color(match (true) {
                    $occupancy >= 85 => 'success',
                    $occupancy >= 60 => 'warning',
                    default => 'danger',
                })
                ->chart([$available, $occupied, $total]),

            Stat::make('Occupied Beds', (string) $occupied)
                ->description($reserved > 0 ? "{$reserved} bed(s) booked for future move-in" : 'No future bookings held')
                ->descriptionIcon('heroicon-m-user')
                ->color('info'),

            Stat::make('Available Beds', (string) $available)
                ->description($available > 0 ? 'Ready to allot right now' : 'House is full')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($available > 0 ? 'success' : 'gray'),

            Stat::make('Under Maintenance', (string) $maintenance)
                ->description($maintenance > 0 ? 'Blocked for repairs — cannot be allotted' : 'All beds serviceable')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($maintenance > 0 ? 'danger' : 'success'),

            Stat::make('Monthly Run-rate', '₹'.number_format($expectedRent))
                ->description('Rent committed by '.Booking::query()->live()->count().' active stays')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Dues Outstanding', '₹'.number_format($dues))
                ->description($dues > 0 ? 'Across unpaid & overdue invoices' : 'Everything is settled')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($dues > 0 ? 'warning' : 'success'),
        ];
    }
}
