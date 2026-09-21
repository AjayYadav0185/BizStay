<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a bed cannot be handed to a guest. Callers inside a transaction
 * let this bubble out so the lock is released and nothing half-written lands
 * in the database.
 */
final class BedAllocationException extends RuntimeException
{
    public static function bedNotAllocatable(string $bedCode, string $status): self
    {
        return new self("Bed {$bedCode} is {$status} and cannot be allocated right now.");
    }

    public static function bedUnderMaintenance(string $bedCode): self
    {
        return new self("Bed {$bedCode} is under maintenance. Release it from maintenance first.");
    }

    public static function guestNotEligible(string $guestName, string $reason): self
    {
        return new self("Guest {$guestName} cannot be allocated a bed: {$reason}.");
    }

    public static function guestAlreadyStaying(string $guestName): self
    {
        return new self("Guest {$guestName} already has a live booking. Check them out first.");
    }

    public static function overlappingStay(string $bedCode): self
    {
        return new self("Bed {$bedCode} already has a booking overlapping these dates.");
    }
}
