<?php

namespace App\Filament\Pages;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\SurveillanceSecurite;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * F85 : comptes suspects (score de signaux sur 24 h) et comptes verrouillés.
 * Actions admin : verrouiller temporairement, déverrouiller, fermer toutes les sessions (UserPolicy).
 */
class ComptesSuspects extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Comptes suspects';

    protected static ?string $title = 'Comptes suspects';

    protected static ?string $slug = 'securite/comptes-suspects';

    protected string $view = 'filament.pages.comptes-suspects';

    /** @var array<int, string> Durées de verrouillage proposées (minutes => libellé). */
    public const DUREES = [
        15 => '15 minutes',
        60 => '1 heure',
        1440 => '24 heures',
        10080 => '7 jours',
    ];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    public function table(Table $table): Table
    {
        $depuis = now()->subDay();
        $poids = "SUM(CASE niveau WHEN 'eleve' THEN 3 WHEN 'moyen' THEN 1 ELSE 0 END)";

        $score = SecurityEvent::query()
            ->selectRaw('COALESCE('.$poids.', 0)')
            ->whereColumn('security_events.user_id', 'users.id')
            ->where('created_at', '>=', $depuis);

        $dernierSignal = SecurityEvent::query()
            ->select('type')
            ->whereColumn('security_events.user_id', 'users.id')
            ->latest('created_at')
            ->limit(1);

        $suspects = SecurityEvent::query()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $depuis)
            ->groupBy('user_id')
            ->havingRaw($poids.' >= ?', [(int) config('security.surveillance.score_suspect')]);

        return $table
            ->query(
                User::query()
                    ->select('users.*')
                    ->selectSub($score, 'score_suspicion')
                    ->selectSub($dernierSignal, 'dernier_signal')
                    ->withCount(['securityEvents as signaux_24h' => fn (Builder $q) => $q->where('created_at', '>=', $depuis)])
                    ->where(fn (Builder $q) => $q
                        ->where('verrouille_jusqu_au', '>', now())
                        ->orWhereIn('users.id', $suspects))
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Compte')
                    ->description(fn (User $record): ?string => $record->emailAffichable())
                    ->searchable(),
                TextColumn::make('score_suspicion')
                    ->label('Score (24 h)')
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= (int) config('security.surveillance.score_verrouillage_auto') ? 'danger' : 'warning')
                    ->sortable(),
                TextColumn::make('signaux_24h')
                    ->label('Signaux (24 h)'),
                TextColumn::make('dernier_signal')
                    ->label('Dernier signal')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (SecurityEvent::TYPE_OPTIONS[$state] ?? $state))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('verrouille_jusqu_au')
                    ->label('Verrouillé jusqu’au')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Non verrouillé')
                    ->badge()
                    ->color(fn (User $record): string => $record->estVerrouille() ? 'danger' : 'gray'),
            ])
            ->defaultSort('score_suspicion', 'desc')
            ->recordActions([
                Action::make('verrouiller')
                    ->label('Verrouiller')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (User $record): bool => Gate::allows('verrouiller', $record))
                    ->modalHeading(fn (User $record): string => 'Verrouiller le compte de '.$record->name)
                    ->modalDescription('Le compte est déconnecté partout et ne peut plus se connecter jusqu’à l’échéance. Le verrou se lève tout seul.')
                    ->modalSubmitActionLabel('Verrouiller')
                    ->schema([
                        Select::make('minutes')
                            ->label('Durée')
                            ->options(self::DUREES)
                            ->default(60)
                            ->required()
                            ->in(array_keys(self::DUREES)),
                        TextInput::make('motif')
                            ->label('Motif (facultatif)')
                            ->maxLength(150),
                    ])
                    ->action(function (User $record, array $data): void {
                        Gate::authorize('verrouiller', $record);

                        $admin = Auth::user();
                        app(SurveillanceSecurite::class)->verrouiller(
                            $record,
                            (int) $data['minutes'],
                            $admin instanceof User ? $admin : null,
                            trim((string) ($data['motif'] ?? '')),
                        );

                        Notification::make()->title('Compte verrouillé et déconnecté.')->success()->send();
                    }),
                Action::make('deverrouiller')
                    ->label('Déverrouiller')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (User $record): bool => Gate::allows('deverrouiller', $record))
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        Gate::authorize('deverrouiller', $record);

                        $admin = Auth::user();
                        app(SurveillanceSecurite::class)->deverrouiller($record, $admin instanceof User ? $admin : null);

                        Notification::make()->title('Compte déverrouillé.')->success()->send();
                    }),
                Action::make('fermerSessions')
                    ->label('Fermer ses sessions')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('gray')
                    ->visible(fn (User $record): bool => Gate::allows('verrouiller', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Le compte sera déconnecté de tous ses appareils et devra se reconnecter.')
                    ->action(function (User $record): void {
                        Gate::authorize('verrouiller', $record);

                        $admin = Auth::user();
                        app(SurveillanceSecurite::class)->fermerSessions($record, $admin instanceof User ? $admin : null);

                        Notification::make()->title('Sessions fermées.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Aucun compte suspect')
            ->emptyStateDescription('Les comptes qui cumulent des signaux d’activité inhabituelle sur 24 h, ou verrouillés, apparaîtront ici.');
    }
}
