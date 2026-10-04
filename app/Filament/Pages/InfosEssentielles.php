<?php

namespace App\Filament\Pages;

use App\Models\ActionLog;
use App\Models\User;
use App\Support\InfosEssentielles as ServiceInfosEssentielles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * F94 : publication par un admin de la consigne d'incident (bandeau en haut de toutes les pages)
 * et de la page « Infos essentielles », régénérée en version statique à chaque modification.
 */
class InfosEssentielles extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Infos essentielles';

    protected static ?string $title = 'Infos essentielles (consigne d’incident)';

    protected static ?string $slug = 'infos-essentielles';

    protected string $view = 'filament.pages.infos-essentielles';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array{titre: string, message: string, niveau: string, publiee_le: string}|null
     */
    public function consigne(): ?array
    {
        return ServiceInfosEssentielles::consigne();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publier')
                ->label(fn (): string => $this->consigne() === null ? 'Publier une consigne' : 'Modifier la consigne')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('warning')
                ->modalHeading('Consigne d’incident')
                ->modalDescription('Affichée en bandeau en haut de toutes les pages et en tête de la page « Infos essentielles ».')
                ->modalSubmitActionLabel('Publier')
                ->fillForm(fn (): array => [
                    'titre' => $this->consigne()['titre'] ?? '',
                    'message' => $this->consigne()['message'] ?? '',
                    'niveau' => $this->consigne()['niveau'] ?? 'alerte',
                ])
                ->schema([
                    TextInput::make('titre')
                        ->label('Titre')
                        ->required()
                        ->maxLength(120),
                    Textarea::make('message')
                        ->label('Consigne')
                        ->helperText('Ce que les habitants doivent savoir ou faire pendant l’incident.')
                        ->required()
                        ->maxLength(1000)
                        ->rows(4),
                    Select::make('niveau')
                        ->label('Niveau')
                        ->options(ServiceInfosEssentielles::NIVEAU_LABELS)
                        ->default('alerte')
                        ->required()
                        ->in(ServiceInfosEssentielles::NIVEAU_OPTIONS),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);

                    ServiceInfosEssentielles::publier((string) $data['titre'], (string) $data['message'], (string) $data['niveau']);
                    ActionLog::record('consigne_incident_publiee');

                    Notification::make()->title('Consigne publiée sur toutes les pages')->success()->send();
                }),
            Action::make('retirer')
                ->label('Retirer la consigne')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->consigne() !== null)
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    ServiceInfosEssentielles::retirer();
                    ActionLog::record('consigne_incident_retiree');

                    Notification::make()->title('Consigne retirée')->success()->send();
                }),
            Action::make('regenerer')
                ->label('Régénérer la page statique')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    if (! ServiceInfosEssentielles::regenerer()) {
                        Notification::make()->title('Régénération impossible : réessayez dans un instant')->danger()->send();

                        return;
                    }

                    Notification::make()->title('Page « Infos essentielles » régénérée')->success()->send();
                }),
            Action::make('voir')
                ->label('Voir la page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('infos-essentielles'), shouldOpenInNewTab: true),
        ];
    }
}
