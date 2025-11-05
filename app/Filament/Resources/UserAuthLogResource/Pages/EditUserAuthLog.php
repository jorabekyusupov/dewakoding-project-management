<?php

namespace App\Filament\Resources\UserAuthLogResource\Pages;

use App\Filament\Resources\UserAuthLogResource\UserAuthLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserAuthLog extends EditRecord
{
    protected static string $resource = UserAuthLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
