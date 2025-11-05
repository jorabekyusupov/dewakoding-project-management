<?php

namespace App\Filament\Resources\UserAuthLogResource\Pages;

use App\Filament\Resources\UserAuthLogResource\UserAuthLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserAuthLog extends CreateRecord
{
    protected static string $resource = UserAuthLogResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
