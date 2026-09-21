<?php

namespace App\Filament\Resources\BedResource\Pages;

use App\Filament\Resources\BedResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBeds extends ManageRecords
{
    protected static string $resource = BedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
