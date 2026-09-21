<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Sub-meter category installed per room. The unit rate is resolved from the
 * singleton property profile unless the meter overrides it.
 */
enum MeterType: string implements HasColor, HasIcon, HasLabel
{
    case Electricity = 'electricity';
    case Water = 'water';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electricity => 'Electricity',
            self::Water => 'Water',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Electricity => 'warning',
            self::Water => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Electricity => 'heroicon-o-bolt',
            self::Water => 'heroicon-o-beaker',
        };
    }

    public function getUnit(): string
    {
        return match ($this) {
            self::Electricity => 'kWh',
            self::Water => 'kL',
        };
    }

    public function getSettingKey(): string
    {
        return match ($this) {
            self::Electricity => 'electricity_rate_per_unit',
            self::Water => 'water_rate_per_unit',
        };
    }

    /**
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
