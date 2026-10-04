<?php

namespace App\Filament\Resources\TentativesBloquees;

use App\Filament\Resources\TentativesBloquees\Pages\ListTentativesBloquees;
use App\Filament\Resources\TentativesBloquees\Tables\TentativesBloqueesTable;
use App\Models\TentativeBloquee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * F81 : envois de formulaires bloqués par la protection anti-robots. Lecture seule, admins (TentativeBloqueePolicy).
 */
class TentativeBloqueeResource extends Resource
{
    protected static ?string $model = TentativeBloquee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $modelLabel = 'envoi bloqué';

    protected static ?string $pluralModelLabel = 'robots bloqués';

    protected static ?string $navigationLabel = 'Robots bloqués';

    protected static ?string $slug = 'robots-bloques';

    public static function table(Table $table): Table
    {
        return TentativesBloqueesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TentativeBloquee::where('created_at', '>=', now()->subDay())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Envois bloqués ces dernières 24 h';
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
            'index' => ListTentativesBloquees::route('/'),
        ];
    }
}
