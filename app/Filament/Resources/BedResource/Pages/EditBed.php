<?php

declare(strict_types=1);

namespace App\Filament\Resources\BedResource\Pages;

use App\Filament\Resources\BedResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBed extends EditRecord
{
    protected static string $resource = BedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
