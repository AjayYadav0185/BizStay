<?php

declare(strict_types=1);

namespace App\Filament\Resources\BedResource\Pages;

use App\Filament\Resources\BedResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBed extends CreateRecord
{
    protected static string $resource = BedResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
