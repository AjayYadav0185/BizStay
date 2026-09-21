<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Growing a room (e.g. triple -> quad) must not silently leave the room
     * short of a bed, so any missing bed is generated on save.
     */
    protected function afterSave(): void
    {
        $created = $this->getRecord()->syncBedsToCapacity();

        if ($created > 0) {
            Notification::make()
                ->title("{$created} bed(s) added to match the sharing type")
                ->success()
                ->send();
        }
    }
}
