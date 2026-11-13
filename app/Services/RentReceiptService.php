<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Guest;
use App\Models\Invoice;
use App\Support\Ncr;
use Illuminate\Support\Carbon;

/**
 * Monthly rent receipts for a resident's HRA claim (Section 10(13A)).
 *
 * One receipt per billed month inside an Indian financial year (Apr–Mar),
 * for the rent actually paid — never the billed amount — so the employee
 * only claims money that really left their account. Utilities, food and
 * late fees are excluded: HRA covers rent only.
 */
final class RentReceiptService
{
    /**
     * Financial year that contains $on, as the April year (2026 = FY 2026-27).
     */
    public static function financialYearOf(?Carbon $on = null): int
    {
        $on ??= now();

        return $on->month >= 4 ? $on->year : $on->year - 1;
    }

    public static function financialYearLabel(int $startYear): string
    {
        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /**
     * @return array{receipts: list<array{month: string, period: string, invoice: string, amount: float, paid_on: ?string}>, total: float, needs_landlord_pan: bool, fy: string}
     */
    public function forGuest(Guest $guest, int $fyStartYear): array
    {
        $from = Carbon::create($fyStartYear, 4, 1)->startOfDay();
        $to = Carbon::create($fyStartYear + 1, 3, 31)->endOfDay();

        $invoices = Invoice::query()
            ->whereIn('booking_id', $guest->bookings()->select('id'))
            ->whereDate('billing_cycle_start', '>=', $from->toDateString())
            ->whereDate('billing_cycle_start', '<=', $to->toDateString())
            ->where('amount_paid', '>', 0)
            ->with(['payments' => fn ($query) => $query->settled()->orderByDesc('paid_on')])
            ->orderBy('billing_cycle_start')
            ->get();

        $receipts = [];

        foreach ($invoices as $invoice) {
            $rentPaid = round(min((float) $invoice->rent_amount + (float) $invoice->gst_amount, (float) $invoice->amount_paid), 2);

            if ($rentPaid <= 0.0) {
                continue;
            }

            $receipts[] = [
                'month' => $invoice->billing_cycle_start->format('F Y'),
                'period' => $invoice->billing_cycle_start->format('d M Y').' – '.$invoice->billing_cycle_end->format('d M Y'),
                'invoice' => $invoice->invoice_number,
                'amount' => $rentPaid,
                'paid_on' => $invoice->payments->first()?->paid_on?->format('d M Y'),
            ];
        }

        $total = round(array_sum(array_column($receipts, 'amount')), 2);

        return [
            'receipts' => $receipts,
            'total' => $total,
            'needs_landlord_pan' => $total > Ncr::hraLandlordPanThreshold(),
            'fy' => self::financialYearLabel($fyStartYear),
        ];
    }
}
