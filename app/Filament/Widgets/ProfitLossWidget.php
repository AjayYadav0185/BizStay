<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Money widget — the "is the house making money?" screen.
 *
 * Cash view: collected from guests (settled payments, deposit adjustments
 * excluded) minus operating expenses for the month. P&L lives on the Property
 * model so tinker, reports and tests can all reuse the same numbers.
 */
final class ProfitLossWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $pnl = Property::current()?->monthlyPnL() ?? ['collected' => 0.0, 'expenses' => 0.0, 'profit' => 0.0];

        $profit = $pnl['profit'];

        return [
            Stat::make('Collected', '₹'.number_format($pnl['collected']))
                ->description('Settled payments this month (excl. deposit adjustments)')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Operating Expenses', '₹'.number_format($pnl['expenses']))
                ->description('Bills, salaries, repairs recorded this month')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color($pnl['expenses'] > $pnl['collected'] ? 'danger' : 'gray'),

            Stat::make('Net (Cash P&L)', '₹'.number_format($profit))
                ->description($profit >= 0 ? 'House is running in the green' : 'Spending is outpacing collection')
                ->descriptionIcon($profit >= 0 ? 'heroicon-m-face-smile' : 'heroicon-m-exclamation-triangle')
                ->color($profit >= 0 ? 'success' : 'danger'),
        ];
    }
}
