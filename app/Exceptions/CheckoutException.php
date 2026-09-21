<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a stay cannot be settled/closed (already closed, booking
 * cancelled, nothing to settle...).
 */
final class CheckoutException extends RuntimeException
{
    public static function bookingNotLive(string $guestName, string $status): self
    {
        return new self("Cannot check out {$guestName}: the booking is {$status}.");
    }

    public static function nothingToSettle(string $guestName): self
    {
        return new self("There is nothing left to settle for {$guestName}.");
    }
}
