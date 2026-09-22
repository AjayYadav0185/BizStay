<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestResource\Pages;

use App\Filament\Resources\GuestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuest extends EditRecord
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $aadhaar = (string) ($data['aadhaar_number'] ?? '');
        unset($data['aadhaar_number']);

        $record->fill($data);
        if ($aadhaar !== '' && $record instanceof \App\Models\Guest) {
            $record->setAadhaarNumber($aadhaar);
        }
        $record->save();

        return $record;
    }
}
