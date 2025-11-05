<?php

namespace App\Filament\Resources\UserAuthLogResource\Pages;

use App\Filament\Resources\UserAuthLogResource\UserAuthLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserAuthLogs extends ListRecords
{
    protected static string $resource = UserAuthLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
