<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\SecurityMonitor;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * F69 : événements de sécurité détectés (403 répétés, CSRF, liens signés, uploads refusés, blocages).
 * Lecture seule, administrateurs uniquement (agent et citoyen : 403). Badge : alertes des dernières 24 h.
 */
class AlertesSecurite extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'alertes-securite';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static ?string $navigationLabel = 'Alertes sécurité';

    protected static ?string $title = 'Alertes sécurité';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.alertes-securite';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewSecurity', AuditLog::class) ?? false;
    }

    public function mount(): void
    {
        Gate::authorize('viewSecurity', AuditLog::class);
    }

    public static function getNavigationBadge(): ?string
    {
        $nombre = AuditLog::query()
            ->where('subject_type', AuditLog::SUJET_SECURITE)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $nombre > 0 ? (string) $nombre : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Événements de sécurité des dernières 24 heures';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AuditLog::query()->where('subject_type', AuditLog::SUJET_SECURITE))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s', AuditLog::FUSEAU)
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Événement')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AuditLog::ACTION_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === SecurityMonitor::BLOCAGE ? 'danger' : 'warning'),
                TextColumn::make('actor_name')
                    ->label('Compte')
                    ->formatStateUsing(fn (string $state, AuditLog $record): string => $record->actor_id ? $record->auteur() : 'Visiteur non connecté')
                    ->searchable(),
                TextColumn::make('subject_label')
                    ->label('Page / route')
                    ->formatStateUsing(fn (AuditLog $record): string => $record->nomElement())
                    ->searchable(),
                TextColumn::make('ip')
                    ->label('IP approximative')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label('Événement')
                    ->options(SecurityMonitor::types()),
            ])
            ->emptyStateHeading('Aucune alerte de sécurité')
            ->emptyStateDescription('Les accès refusés répétés, jetons invalides et blocages apparaîtront ici.');
    }
}
