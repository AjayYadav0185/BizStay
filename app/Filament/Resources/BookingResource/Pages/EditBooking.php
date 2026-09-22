<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Services\BedAllocationService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Bed moves go through the locked service so the old bed is freed
        // and the new bed is occupied atomically (observer alone is not enough).
        if (isset($data['bed_id']) && (int) $data['bed_id'] !== (int) $record->bed_id) {
            /** @var \App\Models\Booking $record */
            app(BedAllocationService::class)->changeBed($record, (int) $data['bed_id']);
            unset($data['bed_id']);
        }

        $record->fill($data);
        $record->save();

        return $record;
    }
}
