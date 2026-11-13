<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Support\Ncr;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner / accountant exports. Every report returns a header row plus data
 * rows so the same numbers feed the CSV download and the on-screen summary.
 */
final class ReportService
{
    /**
     * @return array<string, array{label: string, description: string, dated: bool}>
     */
    public static function catalogue(): array
    {
        return [
            'rent-roll' => ['label' => 'Rent roll', 'description' => 'Every live stay: bed, rent, deposit held, dues, KYC and police verification.', 'dated' => false],
            'dues-aging' => ['label' => 'Dues aging', 'description' => 'Outstanding balance per resident split into current / 1-30 / 31-60 / 61-90 / 90+ days.', 'dated' => false],
            'collections' => ['label' => 'Collections register', 'description' => 'Every rupee received in the period with method and UTR — hand this to your CA.', 'dated' => true],
            'gst-register' => ['label' => 'GST register (hotel stays)', 'description' => 'Taxable value, CGST / SGST split and total per invoice for GSTR-1.', 'dated' => true],
            'expenses' => ['label' => 'Expense register', 'description' => 'Operating spend by category and vendor for the period.', 'dated' => true],
            'police-verification' => ['label' => 'Police verification status', 'description' => 'Residents currently staying and where their tenant verification stands.', 'dated' => false],
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function build(string $report, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        return match ($report) {
            'rent-roll' => $this->rentRoll(),
            'dues-aging' => $this->duesAging(),
            'collections' => $this->collections($from, $to),
            'gst-register' => $this->gstRegister($from, $to),
            'expenses' => $this->expenses($from, $to),
            'police-verification' => $this->policeVerification(),
            default => throw new InvalidArgumentException('Unknown report '.$report),
        };
    }

    public function download(string $report, ?Carbon $from = null, ?Carbon $to = null): StreamedResponse
    {
        [$headers, $rows] = $this->build($report, $from, $to);

        $filename = sprintf(
            '%s-%s%s.csv',
            str(Property::current()?->code ?? 'bizstay')->slug(),
            $report,
            self::catalogue()[$report]['dated'] ? '-'.($from ?? now())->format('Ymd').'-'.($to ?? now())->format('Ymd') : '-'.now()->format('Ymd'),
        );

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel shows ₹ and Hindi names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($value): string => self::safeCell($value), $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function rentRoll(): array
    {
        $rows = Booking::query()
            ->live()
            ->with(['guest', 'bed.room'])
            ->get()
            ->sortBy(fn (Booking $booking): string => (string) $booking->bed?->bed_code, SORT_NATURAL)
            ->map(fn (Booking $booking): array => [
                $booking->bed?->room?->room_number,
                $booking->bed?->bed_code,
                $booking->guest?->full_name,
                $booking->guest?->phone,
                $booking->guest?->company_name,
                strtoupper($booking->stay_type ?? 'pg'),
                $booking->status->getLabel(),
                $booking->check_in_date?->toDateString(),
                $booking->expected_check_out_date?->toDateString(),
                (float) $booking->monthly_rent,
                $booking->depositHeld(),
                $booking->outstandingDues(),
                $booking->guest?->kyc_status?->getLabel(),
                $booking->guest?->police_verification_status?->getLabel(),
            ])
            ->values()
            ->all();

        return [[
            'Room', 'Bed', 'Resident', 'Phone', 'Company', 'Stay type', 'Status', 'Check-in', 'Expected check-out',
            'Monthly rent', 'Deposit held', 'Outstanding', 'KYC', 'Police verification',
        ], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function duesAging(): array
    {
        $buckets = [];

        $invoices = Invoice::query()
            ->outstanding()
            ->with(['booking.guest', 'booking.bed'])
            ->get();

        foreach ($invoices as $invoice) {
            $balance = $invoice->balanceDue();

            if ($balance <= 0.0) {
                continue;
            }

            $key = (string) $invoice->booking_id;
            $buckets[$key] ??= [
                'guest' => $invoice->booking?->guest?->full_name,
                'phone' => $invoice->booking?->guest?->phone,
                'bed' => $invoice->booking?->bed?->bed_code,
                'status' => $invoice->booking?->status?->getLabel(),
                'current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90plus' => 0.0,
            ];

            $daysLate = $invoice->due_date->isFuture() ? 0 : (int) $invoice->due_date->diffInDays(now()->startOfDay());

            $bucket = match (true) {
                $daysLate <= 0 => 'current',
                $daysLate <= 30 => 'd30',
                $daysLate <= 60 => 'd60',
                $daysLate <= 90 => 'd90',
                default => 'd90plus',
            };

            $buckets[$key][$bucket] = round($buckets[$key][$bucket] + $balance, 2);
        }

        $rows = collect($buckets)
            ->map(fn (array $b): array => [
                $b['guest'], $b['phone'], $b['bed'], $b['status'],
                $b['current'], $b['d30'], $b['d60'], $b['d90'], $b['d90plus'],
                round($b['current'] + $b['d30'] + $b['d60'] + $b['d90'] + $b['d90plus'], 2),
            ])
            ->sortByDesc(fn (array $row): float => $row[9])
            ->values()
            ->all();

        return [['Resident', 'Phone', 'Bed', 'Stay status', 'Not yet due', '1-30 days', '31-60 days', '61-90 days', '90+ days', 'Total outstanding'], $rows];
    }

    /**
     * Totals per aging bucket for the on-screen summary.
     *
     * @return array{current: float, d30: float, d60: float, d90: float, d90plus: float, total: float}
     */
    public function duesAgingTotals(): array
    {
        [, $rows] = $this->duesAging();

        $totals = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90plus' => 0.0, 'total' => 0.0];

        foreach ($rows as $row) {
            $totals['current'] += (float) $row[4];
            $totals['d30'] += (float) $row[5];
            $totals['d60'] += (float) $row[6];
            $totals['d90'] += (float) $row[7];
            $totals['d90plus'] += (float) $row[8];
            $totals['total'] += (float) $row[9];
        }

        return array_map(static fn (float $v): float => round($v, 2), $totals);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function collections(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()
            ->settled()
            ->between($from, $to)
            ->with(['invoice', 'booking.guest', 'booking.bed', 'collectedBy'])
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $payment): array => [
                $payment->paid_on?->toDateString(),
                $payment->invoice?->invoice_number,
                $payment->booking?->guest?->full_name,
                $payment->booking?->bed?->bed_code,
                ucfirst($payment->payment_type->value),
                $payment->payment_method->getLabel(),
                $payment->transaction_id,
                $payment->payment_type->isOutflow() ? -1 * (float) $payment->amount : (float) $payment->amount,
                $payment->payment_method === PaymentMethod::DepositAdjustment ? 'No (adjustment)' : 'Yes',
                $payment->collectedBy?->name ?? ($payment->transaction_id && str_starts_with((string) $payment->transaction_id, 'pay_') ? 'Razorpay' : null),
            ])
            ->all();

        return [['Date', 'Invoice', 'Resident', 'Bed', 'Type', 'Method', 'UTR / Ref', 'Amount', 'Cash moved', 'Collected by'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function gstRegister(Carbon $from, Carbon $to): array
    {
        $property = Property::current();
        $propertyState = Ncr::stateFor($property?->city) ?? $property?->state;

        $rows = Invoice::query()
            ->where('gst_amount', '>', 0)
            ->whereDate('billing_cycle_start', '>=', $from->toDateString())
            ->whereDate('billing_cycle_start', '<=', $to->toDateString())
            ->with('booking.guest')
            ->orderBy('invoice_number')
            ->get()
            ->map(function (Invoice $invoice): array {
                $gst = (float) $invoice->gst_amount;
                $half = round($gst / 2, 2);

                return [
                    $invoice->invoice_number,
                    ($invoice->generated_at ?? $invoice->created_at)?->toDateString(),
                    $invoice->booking?->guest?->full_name,
                    '996311',
                    (float) $invoice->rent_amount,
                    (float) $invoice->gst_percent,
                    $half,
                    round($gst - $half, 2),
                    0.0,
                    round((float) $invoice->rent_amount + $gst, 2),
                ];
            })
            ->all();

        return [[
            'Invoice', 'Date', 'Guest', 'SAC', 'Taxable value', 'GST %', 'CGST', 'SGST', 'IGST',
            'Invoice value ('.($propertyState ?? 'intra-state').')',
        ], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function expenses(Carbon $from, Carbon $to): array
    {
        $rows = Expense::query()
            ->between($from, $to)
            ->orderBy('spent_on')
            ->get()
            ->map(fn (Expense $expense): array => [
                $expense->spent_on?->toDateString(),
                $expense->category?->getLabel(),
                $expense->vendor,
                $expense->payment_method?->getLabel(),
                (float) $expense->amount,
                $expense->notes,
            ])
            ->all();

        return [['Date', 'Category', 'Vendor', 'Paid by', 'Amount', 'Notes'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    public function policeVerification(): array
    {
        $rows = Guest::query()
            ->staying()
            ->with('currentBooking.bed')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Guest $guest): array => [
                $guest->full_name,
                $guest->phone,
                $guest->currentBooking?->bed?->bed_code,
                $guest->currentBooking?->check_in_date?->toDateString(),
                $guest->police_verification_status?->getLabel(),
                $guest->police_verification_ref,
                $guest->police_station,
                $guest->police_verification_submitted_on?->toDateString(),
                $guest->police_verified_on?->toDateString(),
            ])
            ->all();

        return [['Resident', 'Phone', 'Bed', 'Check-in', 'Status', 'Reference no.', 'Police station', 'Submitted on', 'Verified on'], $rows];
    }

    /**
     * Neutralises spreadsheet formula injection (a guest named "=HYPERLINK(...)").
     */
    private static function safeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        $string = (string) $value;

        return $string !== '' && in_array($string[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($string)
            ? "'".$string
            : $string;
    }
}
