<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Bed;

/**
 * Keeps a room's derived status glued to the state of its beds. Rooms are never
 * edited by hand for status once this observer is registered.
 */
final class BedObserver
{
    public function saved(Bed $bed): void
    {
        $this->syncRoom($bed);
    }

    public function deleted(Bed $bed): void
    {
        $this->syncRoom($bed);
    }

    public function restored(Bed $bed): void
    {
        $this->syncRoom($bed);
    }

    private function syncRoom(Bed $bed): void
    {
        $room = $bed->room()->first();

        $room?->syncStatusFromBeds();
    }
}
