<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function afterCreate(): void
    {
        $room = $this->getRecord();

        // Auto-create beds A, B, C, ... as per the sharing capacity.
        foreach (range(1, (int) $room->sharing_capacity) as $i) {
            $room->beds()->create(['bed_number' => chr(64 + $i)]);
        }
    }
}
