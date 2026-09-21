<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveLogResource\Pages;

use App\Filament\Resources\LeaveLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveLog extends CreateRecord
{
    protected static string $resource = LeaveLogResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
