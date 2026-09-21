<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FollowUpInquiries;
use App\Filament\Widgets\OccupancyChart;
use App\Filament\Widgets\OpenComplaints;
use App\Filament\Widgets\PendingPayments;
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            PendingPayments::class,
            OpenComplaints::class,
            FollowUpInquiries::class,
            OccupancyChart::class,
        ];
    }
}
