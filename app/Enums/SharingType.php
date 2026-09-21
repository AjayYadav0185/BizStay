<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Sharing type of a room. Drives how many beds are generated for the room
 * and the default per-bed rent band.
 */
enum SharingType: string implements HasColor, HasIcon, HasLabel
{
    case Single = 'single';
    case Double = 'double';
    case Triple = 'triple';
    case Quad = 'quad';

    /**
     * Number of beds this sharing type represents.
     */
    public function capacity(): int
    {
        return match ($this) {
            self::Single => 1,
            self::Double => 2,
            self::Triple => 3,
            self::Quad => 4,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Single Sharing',
            self::Double => 'Double Sharing',
            self::Triple => 'Triple Sharing',
            self::Quad => 'Quad Sharing',
        };
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Double => 'Double',
            self::Triple => 'Triple',
            self::Quad => 'Quad',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Single => 'primary',
            self::Double => 'info',
            self::Triple => 'warning',
            self::Quad => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Single => 'heroicon-o-user',
            self::Double => 'heroicon-o-user-group',
            self::Triple => 'heroicon-o-users',
            self::Quad => 'heroicon-o-user-plus',
        };
    }

    /**
     * All sharing types keyed by their value, for form select options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
