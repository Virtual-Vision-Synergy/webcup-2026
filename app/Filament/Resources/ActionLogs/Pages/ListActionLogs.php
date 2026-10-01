<?php

namespace App\Filament\Resources\ActionLogs\Pages;

use App\Filament\Resources\ActionLogs\ActionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActionLogs extends ListRecords
{
    protected static string $resource = ActionLogResource::class;
}
