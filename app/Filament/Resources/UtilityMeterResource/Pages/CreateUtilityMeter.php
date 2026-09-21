<?php

declare(strict_types=1);

namespace App\Filament\Resources\UtilityMeterResource\Pages;

use App\Filament\Resources\UtilityMeterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUtilityMeter extends CreateRecord
{
    protected static string $resource = UtilityMeterResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
