<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BedStatus;
use App\Enums\BookingStatus;
use App\Exceptions\BedAllocationException;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only supported way to put a guest on a bed.
 *
 * Race-condition prevention: the whole operation runs in a transaction that
 * takes a row lock (SELECT ... FOR UPDATE) on the bed before it validates
 * availability and re-checks for a live booking. Two managers clicking "Allot"
 * on the same bed therefore serialise: the second transaction re-reads the bed
 * state, sees it is no longer allocatable, and fails cleanly instead of
 * double-booking. (SQLite serialises writers already; MySQL/PostgreSQL get the
 * real lock.)
 */
final class BedAllocationService
{
    public function __construct(private readonly ProrationEngine $proration) {}

    /**
     * Allocates a bed to a guest and opens the stay contract.
     *
     * @param  array{
     *     check_in_date: string,
     *     expected_check_out_date?: string|null,
     *     monthly_rent?: float|string|null,
     *     security_deposit_amount?: float|string|null,
     *     rent_due_day?: int|string|null,
     *     food_included?: bool,
     *     notes?: string|null,
     *     bypass_kyc_check?: bool
     * }  $terms
     *
     * @throws BedAllocationException
     */
    public function allocate(Guest $guest, int $bedId, array $terms): Booking
    {
        return DB::transaction(function () use ($guest, $bedId, $terms): Booking {
            /** @var Bed $bed */
            $bed = Bed::query()->with('room')->lockForUpdate()->findOrFail($bedId);

            if ($bed->status === BedStatus::Maintenance) {
                throw BedAllocationException::bedUnderMaintenance($bed->bed_code);
            }

            if (! $bed->isAllocatable()) {
                throw BedAllocationException::bedNotAllocatable($bed->bed_code, $bed->status->value);
            }

            // Re-checked inside the lock: the authoritative guard.
            if ($this->hasLiveBooking($bed->id)) {
                throw BedAllocationException::overlappingStay($bed->bed_code);
            }

            /** @var Guest $lockedGuest */
            $lockedGuest = Guest::query()->lockForUpdate()->findOrFail($guest->id);

            $this->assertGuestEligible($lockedGuest, (bool) ($terms['bypass_kyc_check'] ?? false));

            if ($this->guestHasLiveBooking($lockedGuest->id)) {
                throw BedAllocationException::guestAlreadyStaying($lockedGuest->full_name);
            }

            $checkIn = Carbon::parse($terms['check_in_date'])->startOfDay();

            $rent = isset($terms['monthly_rent']) && $terms['monthly_rent'] !== null
                ? round((float) $terms['monthly_rent'], 2)
                : $bed->effectiveRent();

            $booking = Booking::query()->create([
                'guest_id' => $lockedGuest->id,
                'bed_id' => $bed->id,
                'check_in_date' => $checkIn->toDateString(),
                'expected_check_out_date' => isset($terms['expected_check_out_date']) && $terms['expected_check_out_date']
                    ? Carbon::parse($terms['expected_check_out_date'])->toDateString()
                    : null,
                'monthly_rent' => $rent,
                'security_deposit_amount' => $this->resolveDeposit($terms, $rent),
                'rent_due_day' => (int) ($terms['rent_due_day'] ?? 5),
                'food_included' => (bool) ($terms['food_included'] ?? true),
                'status' => BookingStatus::Active,
                'notes' => $terms['notes'] ?? null,
            ]);

            // BookingObserver has already moved the bed to Occupied/Reserved.
            return $booking->refresh()->load('bed.room', 'guest');
        });
    }

    /**
     * Moves a live stay to another bed (room change / upgrade).
     *
     * @throws BedAllocationException
     */
    public function changeBed(Booking $booking, int $newBedId): Booking
    {
        return DB::transaction(function () use ($booking, $newBedId): Booking {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->isLive()) {
                throw BedAllocationException::guestNotEligible(
                    $locked->guest?->full_name ?? 'Guest',
                    'the booking is already closed',
                );
            }

            /** @var Bed $bed */
            $bed = Bed::query()->lockForUpdate()->findOrFail($newBedId);

            if ($bed->id === $locked->bed_id) {
                return $locked;
            }

            if ($bed->status === BedStatus::Maintenance) {
                throw BedAllocationException::bedUnderMaintenance($bed->bed_code);
            }

            if (! $bed->isAllocatable() || $this->hasLiveBooking($bed->id)) {
                throw BedAllocationException::bedNotAllocatable($bed->bed_code, $bed->status->value);
            }

            $locked->bed_id = $bed->id;
            $locked->save();

            // BookingObserver frees the old bed and occupies the new one.
            return $locked->refresh()->load('bed.room');
        });
    }

    /**
     * Serves notice: the guest is still billed and still occupies the bed.
     */
    public function putOnNotice(Booking $booking, ?Carbon $servedOn = null): Booking
    {
        $booking->forceFill([
            'status' => BookingStatus::NoticePeriod,
            'notice_served_on' => ($servedOn ?? now())->toDateString(),
        ])->save();

        return $booking->refresh();
    }

    /**
     * Steps a booking back from notice (guest changed their mind).
     */
    public function withdrawNotice(Booking $booking): Booking
    {
        $booking->forceFill([
            'status' => BookingStatus::Active,
            'notice_served_on' => null,
        ])->save();

        return $booking->refresh();
    }

    /**
     * Cancels a stay that never really started (booking dropped before
     * check-in). The bed goes back to the pool via the observer.
     */
    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        $booking->forceFill([
            'status' => BookingStatus::CheckedOut,
            'actual_check_out_date' => now()->toDateString(),
            'checked_out_at' => now(),
            'checkout_settlement' => ['cancelled' => true, 'reason' => $reason],
            'notes' => trim(($booking->notes ?? '')."\nCancelled: ".($reason ?? 'no reason recorded')),
        ])->save();

        return $booking->refresh();
    }

    /**
     * Takes a bed out of service (repair, painting, broken cot).
     *
     * @throws BedAllocationException
     */
    public function takeBedOffline(Bed $bed, ?string $note = null): Bed
    {
        if ($this->hasLiveBooking($bed->id)) {
            throw BedAllocationException::bedNotAllocatable($bed->bed_code, 'occupied by a live stay');
        }

        $bed->sendToMaintenance($note);

        return $bed->refresh();
    }

    public function bringBedOnline(Bed $bed): Bed
    {
        $bed->releaseFromMaintenance();

        return $bed->refresh();
    }

    private function hasLiveBooking(int $bedId): bool
    {
        return Booking::query()->where('bed_id', $bedId)->live()->exists();
    }

    private function guestHasLiveBooking(int $guestId): bool
    {
        return Booking::query()->where('guest_id', $guestId)->live()->exists();
    }

    /**
     * @throws BedAllocationException
     */
    private function assertGuestEligible(Guest $guest, bool $bypassKycCheck): void
    {
        if ($guest->isBlacklisted()) {
            throw BedAllocationException::guestNotEligible($guest->full_name, 'the guest is blacklisted');
        }

        if (! $bypassKycCheck && ! $guest->kyc_status->allowsAllocation()) {
            throw BedAllocationException::guestNotEligible(
                $guest->full_name,
                'KYC status is '.$guest->kyc_status->getLabel(),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function resolveDeposit(array $terms, float $rent): float
    {
        if (array_key_exists('security_deposit_amount', $terms) && $terms['security_deposit_amount'] !== null) {
            return round((float) $terms['security_deposit_amount'], 2);
        }

        $months = max(1, (int) (Property::current()?->security_deposit_months ?? 1));

        return round($rent * $months, 2);
    }
}