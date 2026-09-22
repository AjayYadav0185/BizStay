<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Guest;
use App\Services\BedAllocationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $guestId = (int) $data['guest_id'];
        $bedId = (int) $data['bed_id'];
        unset($data['guest_id'], $data['bed_id']);

        return app(BedAllocationService::class)->allocate(
            Guest::query()->findOrFail($guestId),
            $bedId,
            [
                'check_in_date' => (string) ($data['check_in_date'] ?? now()->toDateString()),
                'expected_check_out_date' => $data['expected_check_out_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'] ?? null,
                'security_deposit_amount' => $data['security_deposit_amount'] ?? null,
                'rent_due_day' => $data['rent_due_day'] ?? 5,
                'food_included' => (bool) ($data['food_included'] ?? true),
                'notes' => $data['notes'] ?? null,
            ]
        );
    }
}
