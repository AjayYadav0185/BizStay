<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveLogResource\Pages;

use App\Filament\Resources\LeaveLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeaveLog extends EditRecord
{
    protected static string $resource = LeaveLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
