<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Property;
use App\Models\User;
use App\Services\ReportService;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * One-click CSV exports for the owner and their CA, plus the numbers a
 * manager checks every week (dues aging, this month's P&L).
 */
class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.reports';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageFinance();
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function setRange(string $range): void
    {
        [$from, $to] = match ($range) {
            'last-month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [now()->firstOfQuarter(), now()->lastOfQuarter()],
            'fy' => [
                Carbon::create(now()->month >= 4 ? now()->year : now()->year - 1, 4, 1),
                Carbon::create(now()->month >= 4 ? now()->year + 1 : now()->year, 3, 31),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $from = $this->parseDate($this->from) ?? now()->startOfMonth();
        $to = $this->parseDate($this->to) ?? now()->endOfMonth();

        return [
            'catalogue' => ReportService::catalogue(),
            'aging' => app(ReportService::class)->duesAgingTotals(),
            'pnl' => Property::current()?->monthlyPnL() ?? ['collected' => 0.0, 'expenses' => 0.0, 'profit' => 0.0],
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
        ];
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
