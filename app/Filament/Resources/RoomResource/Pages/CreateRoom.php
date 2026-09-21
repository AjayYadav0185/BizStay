<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    /**
     * Beds are part of the room's identity: they are generated from the sharing
     * type immediately so the room can never exist without sellable inventory.
     */
    protected function afterCreate(): void
    {
        $created = $this->getRecord()->syncBedsToCapacity();

        Notification::make()
            ->title("{$created} bed(s) created")
            ->body('Bed codes follow the room number, e.g. '.$this->getRecord()->bedCodeFor(1).'.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
