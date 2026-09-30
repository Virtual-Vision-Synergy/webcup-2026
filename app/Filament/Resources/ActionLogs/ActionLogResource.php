<?php

namespace App\Filament\Resources\ActionLogs;

use App\Filament\Resources\ActionLogs\Pages\ListActionLogs;
use App\Filament\Resources\ActionLogs\Tables\ActionLogsTable;
use App\Models\ActionLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des actions : lecture seule (ni création, ni modification, ni suppression).
 */
class ActionLogResource extends Resource
{
    protected static ?string $model = ActionLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'entrée du journal';

    protected static ?string $pluralModelLabel = 'journal des actions';

    protected static ?string $navigationLabel = 'Journal des actions';

    public static function table(Table $table): Table
    {
        return ActionLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActionLogs::route('/'),
        ];
    }
}
