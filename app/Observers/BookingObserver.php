<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Models\Booking;

/**
 * The automatic bed state machine.
 *
 * Booking lifecycle            -> Bed state
 * ------------------------------------------------
 * created, check-in future     -> Reserved
 * created / activated (today)  -> Occupied
 * notice period                -> Occupied (guest still lives here)
 * checked out / cancelled      -> Available (if no other live booking)
 * soft deleted                 -> Available (if no other live booking)
 *
 * A bed explicitly parked under maintenance is never overridden here — that is
 * a physical constraint a manager set on purpose.
 */
final class BookingObserver
{
    public function created(Booking $booking): void
    {
        $this->syncBedFor($booking);
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('bed_id')) {
            $previousBed = Bed::query()->find($booking->getOriginal('bed_id'));

            if ($previousBed) {
                $this->releaseBedIfIdle($previousBed, (int) $booking->id);
            }
        }

        if ($booking->wasChanged(['status', 'check_in_date', 'bed_id'])) {
            $this->syncBedFor($booking);
        }
    }

    public function deleted(Booking $booking): void
    {
        $this->syncBedFor($booking);
    }

    public function restored(Booking $booking): void
    {
        $this->syncBedFor($booking);
    }

    private function syncBedFor(Booking $booking): void
    {
        $bed = Bed::query()->find($booking->bed_id);

        if (! $bed) {
            return;
        }

        // A bed under maintenance must be released deliberately.
        if ($bed->status === BedStatus::Maintenance) {
            return;
        }

        if ($booking->trashed() || ! $booking->status->occupiesBed()) {
            $this->releaseBedIfIdle($bed, (int) $booking->id);

            return;
        }

        $target = $booking->check_in_date->isFuture()
            ? BedStatus::Reserved
            : BedStatus::Occupied;

        if ($bed->status !== $target) {
            $bed->status = $target;
            $bed->save();
        }
    }

    /**
     * Hands a bed back to the pool unless another live booking still holds it.
     */
    private function releaseBedIfIdle(Bed $bed, ?int $ignoreBookingId = null): void
    {
        $stillHeld = Booking::query()
            ->where('bed_id', $bed->id)
            ->live()
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->exists();

        if ($stillHeld || $bed->status === BedStatus::Available) {
            return;
        }

        $bed->status = BedStatus::Available;
        $bed->save();
    }
}
