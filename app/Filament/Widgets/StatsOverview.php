<?php

namespace App\Filament\Widgets;

use App\Enums\ComplaintPriority;
use App\Enums\PaymentStatus;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $blockedBeds = Bed::where('status', 'blocked')->count();
        $vacantBeds = max(0, $totalBeds - $occupiedBeds - $blockedBeds);
        $occupancy = $totalBeds > 0 ? round($occupiedBeds / $totalBeds * 100) : 0;

        $activeTenants = Tenant::whereIn('status', ['active', 'notice_period'])->count();
        $onNotice = Tenant::where('status', 'notice_period')->count();

        $collectedThisMonth = Payment::where('status', 'paid')
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $expectedThisMonth = Tenant::whereIn('status', ['active', 'notice_period'])->sum('monthly_rent');

        $pendingDues = Payment::whereIn('status', ['pending', 'overdue'])->sum('amount');
        $overdueCount = Payment::where('status', PaymentStatus::Overdue->value)->count();

        $openComplaints = Complaint::whereIn('status', ['open', 'in_progress'])->count();
        $highPriority = Complaint::where('priority', ComplaintPriority::High->value)
            ->whereIn('status', ['open', 'in_progress'])->count();

        $newInquiries = Booking::whereNull('property_id')->orWhere('created_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Occupancy', $occupancy.'%')
                ->description("{$occupiedBeds} of {$totalBeds} beds occupied · {$vacantBeds} vacant")
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($occupancy >= 90 ? 'success' : ($occupancy >= 60 ? 'warning' : 'danger'))
                ->chart([$occupiedBeds, $totalBeds]),

            Stat::make('Active Tenants', $activeTenants)
                ->description($onNotice > 0 ? "{$onNotice} on notice period" : 'All stable')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Collected This Month', '₹'.number_format((float) $collectedThisMonth))
                ->description('Expected ₹'.number_format((float) $expectedThisMonth))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($collectedThisMonth >= $expectedThisMonth ? 'success' : 'warning'),

            Stat::make('Pending Dues', '₹'.number_format((float) $pendingDues))
                ->description($overdueCount > 0 ? "{$overdueCount} overdue payment(s)!" : 'No overdue payments')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pendingDues > 0 ? ($overdueCount > 0 ? 'danger' : 'warning') : 'success'),

            Stat::make('Open Complaints', $openComplaints)
                ->description($highPriority > 0 ? "{$highPriority} high priority!" : 'Nothing urgent')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($highPriority > 0 ? 'danger' : ($openComplaints > 0 ? 'warning' : 'success')),

            Stat::make('New Inquiries', Booking::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count())
                ->description('This month (walk-ins & portals)')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('primary'),
        ];
    }
}
