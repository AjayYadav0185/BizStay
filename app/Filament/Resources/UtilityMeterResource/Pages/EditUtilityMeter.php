<?php

declare(strict_types=1);

namespace App\Filament\Resources\UtilityMeterResource\Pages;

use App\Filament\Resources\UtilityMeterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUtilityMeter extends EditRecord
{
    protected static string $resource = UtilityMeterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
