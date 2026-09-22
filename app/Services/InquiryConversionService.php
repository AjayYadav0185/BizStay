<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Enums\KycStatus;
use App\Exceptions\BedAllocationException;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-click "lead -> paying tenant".
 *
 * Creates (or reuses) the Guest, verifies the KYC the manager just saw, parks
 * the guest on a Reserved bed, and raises the first prorated invoice — all in
 * one transaction. Nothing here invents money: the first bill always goes
 * through InvoiceService so the ledger keeps a single writer.
 */
final class InquiryConversionService
{
    public function __construct(
        private readonly BedAllocationService $allocation,
        private readonly InvoiceService $invoices,
        private readonly ProrationEngine $proration,
    ) {}

    /**
     * @param  array{
     *     full_name?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     bed_id: int,
     *     check_in_date: string,
     *     expected_check_out_date?: string|null,
     *     monthly_rent?: float|string|null,
     *     security_deposit_amount?: float|string|null,
     *     rent_due_day?: int|string|null,
     *     food_included?: bool,
     *     verify_kyc?: bool,
     *     notes?: string|null
     * }  $data
     * @return array{guest: Guest, booking: Booking, invoice: Invoice|null}
     *
     * @throws BedAllocationException
     */
    public function convert(Inquiry $inquiry, array $data): array
    {
        return DB::transaction(function () use ($inquiry, $data): array {
            /** @var Inquiry $locked */
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

            if (! $locked->status->isOpen() || $locked->converted_guest_id !== null) {
                $locked->status = InquiryStatus::Converted;
                $locked->save();

                $guest = Guest::query()->findOrFail($locked->converted_guest_id);

                return [
                    'guest' => $guest,
                    'booking' => $guest->currentBooking()->first() ?? Booking::query()->findOrFail(0),
                    'invoice' => null,
                ];
            }

            // 1. Guest: reuse the row if this lead walked in before.
            $guest = Guest::query()->firstOrCreate(
                ['phone' => (string) ($data['phone'] ?? $locked->phone)],
                [
                    'full_name' => (string) ($data['full_name'] ?? $locked->name),
                    'email' => $data['email'] ?? $locked->email,
                    'gender' => $locked->gender,
                ],
            );

            // The manager physically saw the ID at the desk — the whole point of
            // a one-click convert is not to bounce the guest back to KYC queue.
            if ($data['verify_kyc'] ?? true) {
                $guest->forceFill([
                    'kyc_status' => KycStatus::Verified,
                    'kyc_verified_at' => now(),
                    'kyc_remarks' => 'ID verified at inquiry conversion',
                ])->save();
            }

            // 2. Park the bed so nobody else can allot it while paperwork runs.
            Bed::query()->lockForUpdate()->findOrFail((int) $data['bed_id']);

            $terms = [
                'check_in_date' => $data['check_in_date'],
                'expected_check_out_date' => $data['expected_check_out_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'] ?? null,
                'security_deposit_amount' => $data['security_deposit_amount'] ?? null,
                'rent_due_day' => $data['rent_due_day'] ?? null,
                'food_included' => (bool) ($data['food_included'] ?? true),
                'notes' => $data['notes'] ?? null,
            ];

            $booking = $this->allocation->allocate($guest, (int) $data['bed_id'], $terms);

            // 3. First bill: prorated from the actual check-in date. Only when
            // the stay has already begun — future move-ins are billed on arrival.
            $checkIn = Carbon::parse($data['check_in_date'])->startOfDay();
            $invoice = null;

            if ($checkIn->lessThanOrEqualTo(now()->startOfDay())) {
                [$cycleStart, $cycleEnd] = $this->proration->firstCycleFor($booking);
                $invoice = $this->invoices->generateForBooking($booking, $cycleStart, $cycleEnd);
            }

            // 4. Close the lead.
            $locked->forceFill([
                'status' => InquiryStatus::Converted,
                'converted_guest_id' => $guest->id,
            ])->save();

            return ['guest' => $guest, 'booking' => $booking, 'invoice' => $invoice];
        });
    }

    /**
     * Bed options for the conversion form, with the rent each would bill.
     *
     * @return array<int, string>
     */
    public function allocatableBedOptions(): array
    {
        return Bed::query()
            ->with('room')
            ->allocatable()
            ->orderBy('bed_code')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Bed $bed): array => [
                $bed->id => $bed->bed_code.' · Floor '.$bed->room?->floor_no.' · '.$bed->room?->room_number.' · ₹'.number_format($bed->effectiveRent()).'/mo',
            ])
            ->all();
    }

    /**
     * Default move-in rent for the picked bed (used to prefill the form).
     */
    public function defaultRentFor(int $bedId): float
    {
        return (float) (Bed::query()->with('room')->find($bedId)?->effectiveRent() ?? 0);
    }

    /**
     * Default deposit for the picked bed, from the property's deposit policy.
     */
    public function defaultDepositFor(float $rent): float
    {
        $months = max(1, (int) (Property::current()?->security_deposit_months ?? 1));

        return round($rent * $months, 2);
    }
}
