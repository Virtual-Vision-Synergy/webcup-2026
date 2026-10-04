<?php

namespace App\Filament\Pages;

use App\Jobs\LancerSauvegarde;
use App\Models\User;
use App\Models\VerificationSauvegarde;
use App\Services\Sauvegardes as ServiceSauvegardes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;

/**
 * F87 : état des sauvegardes de la base, sauvegarde à la demande, vérification et historique.
 * Réservé aux admins ; aucune restauration ni suppression possible depuis cette page.
 */
class Sauvegardes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Sauvegardes';

    protected static ?string $title = 'Sauvegardes de la base';

    protected static ?string $slug = 'sauvegardes';

    protected string $view = 'filament.pages.sauvegardes';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array{nom: string, taille: string, date: string, age: string}|null
     */
    public function getDerniere(): ?array
    {
        $derniere = app(ServiceSauvegardes::class)->derniere();

        if ($derniere === null) {
            return null;
        }

        return [
            'nom' => $derniere['nom'],
            'taille' => Number::fileSize($derniere['taille'], precision: 1),
            'date' => $derniere['date']->format('d/m/Y à H:i'),
            'age' => $derniere['date']->diffForHumans(),
        ];
    }

    /**
     * @return array{niveau: string, libelle: string, couleur: string}
     */
    public function getEtat(): array
    {
        return app(ServiceSauvegardes::class)->etat();
    }

    public function getDerniereVerification(): ?VerificationSauvegarde
    {
        return VerificationSauvegarde::query()->latest('id')->first();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('lancer')
                ->label('Lancer une sauvegarde maintenant')
                ->icon(Heroicon::OutlinedPlay)
                ->requiresConfirmation()
                ->modalDescription('Une copie complète de la base est créée sur le serveur puis vérifiée. Cela peut prendre quelques secondes.')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    if (! $this->limiter('lancer', 3)) {
                        return;
                    }

                    $user = Auth::user();
                    LancerSauvegarde::dispatch($user instanceof User ? $user->id : null);

                    Notification::make()
                        ->title('Sauvegarde lancée')
                        ->body('Le rapport apparaîtra dans l’historique dès la fin de l’export.')
                        ->success()
                        ->send();
                }),
            Action::make('verifier')
                ->label('Vérifier la dernière sauvegarde')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    if (! $this->limiter('verifier', 6)) {
                        return;
                    }

                    $user = Auth::user();
                    $verification = app(ServiceSauvegardes::class)->verifierDerniere($user instanceof User ? $user : null);

                    $notification = Notification::make()->title($verification->rapport);
                    $verification->estComplete() ? $notification->success() : $notification->danger();
                    $notification->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(VerificationSauvegarde::query()->with('user:id,name'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Vérifiée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Résultat')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => VerificationSauvegarde::STATUT_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        VerificationSauvegarde::COMPLETE => 'success',
                        VerificationSauvegarde::INCOMPLETE => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('rapport')
                    ->label('Rapport')
                    ->wrap(),
                TextColumn::make('nb_tables')
                    ->label('Tables')
                    ->formatStateUsing(fn (int $state, VerificationSauvegarde $record): string => $state.' / '.$record->nb_tables_base)
                    ->toggleable(),
                TextColumn::make('taille')
                    ->label('Taille')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? Number::fileSize($state, precision: 1) : '—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Par')
                    ->placeholder('Automatique')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucune vérification pour l’instant')
            ->emptyStateDescription('Cliquez sur « Vérifier la dernière sauvegarde » pour obtenir un premier rapport.');
    }

    /**
     * Limite les exports et vérifications (opérations lourdes) par admin.
     */
    private function limiter(string $action, int $max): bool
    {
        $cle = 'sauvegardes:'.$action.':'.Auth::id();

        if (RateLimiter::tooManyAttempts($cle, $max)) {
            Notification::make()
                ->title('Trop de demandes')
                ->body('Réessayez dans '.RateLimiter::availableIn($cle).' secondes.')
                ->warning()
                ->send();

            return false;
        }

        RateLimiter::hit($cle, 600);

        return true;
    }
}
