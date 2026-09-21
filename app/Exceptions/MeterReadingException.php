<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Meter readings are physical facts: a lower reading than the previous one
 * means the dial was reset or the manager mistyped. We refuse to bill on it.
 */
final class MeterReadingException extends RuntimeException
{
    public static function regression(string $meterLabel, float $previous, float $current): self
    {
        return new self(sprintf(
            'Reading %s for meter %s is lower than the previous reading %s.',
            number_format($current, 2),
            $meterLabel,
            number_format($previous, 2),
        ));
    }

    public static function duplicateEntry(string $meterLabel, string $date): self
    {
        return new self("A reading for meter {$meterLabel} already exists on {$date}.");
    }

    public static function inactiveMeter(string $meterLabel): self
    {
        return new self("Meter {$meterLabel} is inactive and cannot accept readings.");
    }
}
