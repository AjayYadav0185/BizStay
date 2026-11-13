<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\KycStatus;
use App\Filament\Resources\ComplaintResource;
use App\Filament\Resources\GuestResource;
use App\Filament\Resources\InquiryResource;
use App\Filament\Resources\VisitorResource;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Guest;
use App\Models\Inquiry;
use App\Models\Visitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The morning to-do list: compliance gaps, people inside the gate, leads to
 * call back and tickets past their SLA. Each tile links to the filtered list.
 */
final class AttentionNeededWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '120s';

    protected function getHeading(): ?string
    {
        return 'Needs attention today';
    }

    protected function getStats(): array
    {
        $policePending = Guest::query()->staying()->policeVerificationPending()->count();
        $kycPending = Guest::query()->staying()->where('kyc_status', '!=', KycStatus::Verified->value)->count();
        $visitorsInside = Visitor::query()->inside()->count();
        $overnight = Visitor::query()->inside()->where('is_overnight', true)->count();

        $followUps = Inquiry::query()->open()
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', now()->toDateString())
            ->count();

        $openComplaints = Complaint::query()->open()->get();
        $breached = $openComplaints->filter(fn (Complaint $complaint): bool => $complaint->slaBreached())->count();

        $leavingSoon = Booking::query()->live()
            ->whereNotNull('expected_check_out_date')
            ->whereDate('expected_check_out_date', '>=', now()->toDateString())
            ->whereDate('expected_check_out_date', '<=', now()->addDays(7)->toDateString())
            ->count();

        return [
            Stat::make('Police verification pending', (string) $policePending)
                ->description($policePending > 0 ? 'Residents staying without verification' : 'All residents verified')
                ->descriptionIcon($policePending > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-shield-check')
                ->color($policePending > 0 ? 'danger' : 'success')
                ->url(GuestResource::getUrl('index', ['tableFilters' => ['police_pending' => ['isActive' => true]]])),

            Stat::make('KYC pending', (string) $kycPending)
                ->description($kycPending > 0 ? 'ID not verified for current residents' : 'All IDs verified')
                ->descriptionIcon('heroicon-m-identification')
                ->color($kycPending > 0 ? 'warning' : 'success')
                ->url(GuestResource::getUrl('index', ['tableFilters' => ['kyc_status' => ['value' => KycStatus::Pending->value]]])),

            Stat::make('Visitors inside', (string) $visitorsInside)
                ->description($overnight > 0 ? $overnight.' overnight' : 'Gate register')
                ->descriptionIcon('heroicon-m-identification')
                ->color($visitorsInside > 0 ? 'info' : 'gray')
                ->url(VisitorResource::getUrl('index', ['tableFilters' => ['inside' => ['isActive' => true]]])),

            Stat::make('Leads to call', (string) $followUps)
                ->description('Follow-ups due today or overdue')
                ->descriptionIcon('heroicon-m-phone-arrow-up-right')
                ->color($followUps > 0 ? 'warning' : 'gray')
                ->url(InquiryResource::getUrl('index')),

            Stat::make('Complaints open', (string) $openComplaints->count())
                ->description($breached > 0 ? $breached.' past SLA' : 'All within SLA')
                ->descriptionIcon('heroicon-m-wrench')
                ->color($breached > 0 ? 'danger' : ($openComplaints->isNotEmpty() ? 'warning' : 'success'))
                ->url(ComplaintResource::getUrl('index')),

            Stat::make('Leaving in 7 days', (string) $leavingSoon)
                ->description('Plan settlement + re-letting')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color($leavingSoon > 0 ? 'info' : 'gray'),
        ];
    }
}
