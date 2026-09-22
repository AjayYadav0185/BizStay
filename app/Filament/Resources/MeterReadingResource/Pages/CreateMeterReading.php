<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeterReadingResource\Pages;

use App\Filament\Resources\MeterReadingResource;
use App\Models\UtilityMeter;
use App\Services\MeterReadingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMeterReading extends CreateRecord
{
    protected static string $resource = MeterReadingResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $meter = UtilityMeter::query()->findOrFail((int) $data['meter_id']);

        return app(MeterReadingService::class)->record($meter, [
            'reading_date' => (string) $data['reading_date'],
            'current_reading' => $data['current_reading'],
            'notes' => $data['notes'] ?? null,
        ], auth()->id());
    }
}
