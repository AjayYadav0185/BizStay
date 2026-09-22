<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestResource\Pages;

use App\Filament\Resources\GuestResource;
use App\Models\Guest;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGuest extends CreateRecord
{
    protected static string $resource = GuestResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $aadhaar = (string) ($data['aadhaar_number'] ?? '');
        unset($data['aadhaar_number']);

        /** @var Guest $guest */
        $guest = new Guest($data);
        if ($aadhaar !== '') {
            $guest->setAadhaarNumber($aadhaar);
        }
        $guest->save();

        return $guest;
    }
}
