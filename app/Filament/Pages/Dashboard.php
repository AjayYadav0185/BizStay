<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\DueInvoicesTable;
use App\Filament\Widgets\OccupancyByFloorChart;
use App\Filament\Widgets\OccupancyOverview;
use App\Filament\Widgets\StayMovementsTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $title = 'Occupancy Dashboard';

    public function getColumns(): int|string|array
    {
        return 1;
    }

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return [
            StayMovementsTable::class,
            DueInvoicesTable::class,
            OccupancyByFloorChart::class,
        ];
    }

    /**
     * @return array<int, class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [
            OccupancyOverview::class,
        ];
    }
}

