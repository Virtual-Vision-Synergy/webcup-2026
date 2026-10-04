<?php

namespace App\Filament\Resources\TentativesBloquees\Pages;

use App\Filament\Resources\TentativesBloquees\TentativeBloqueeResource;
use App\Filament\Widgets\ProtectionRobotsOverview;
use Filament\Resources\Pages\ListRecords;

class ListTentativesBloquees extends ListRecords
{
    protected static string $resource = TentativeBloqueeResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ProtectionRobotsOverview::class];
    }
}
