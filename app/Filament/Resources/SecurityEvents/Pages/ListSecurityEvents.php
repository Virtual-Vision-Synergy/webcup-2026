<?php

namespace App\Filament\Resources\SecurityEvents\Pages;

use App\Filament\Resources\SecurityEvents\SecurityEventResource;
use App\Filament\Widgets\SecuriteOverview;
use Filament\Resources\Pages\ListRecords;

class ListSecurityEvents extends ListRecords
{
    protected static string $resource = SecurityEventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [SecuriteOverview::class];
    }
}
