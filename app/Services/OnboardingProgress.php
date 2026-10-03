<?php

namespace App\Services;

use App\Models\Onboarding;
use App\Models\Service;
use App\Models\User;

/**
 * Parcours de prise en main des nouveaux habitants (D12) : compléter le profil → trouver un service
 * → commencer une démarche. La progression est calculée à partir des vraies données du compte.
 */
class OnboardingProgress
{
    /** Clé de session : l'habitant a ouvert le parcours, on l'y ramène après chaque étape. */
    public const SESSION_DEPUIS_PARCOURS = 'onboarding.depuis_parcours';

    public const TOTAL_ETAPES = 3;

    private ?bool $demarcheCommencee = null;

    public function __construct(public readonly User $user) {}

    public static function pour(User $user): self
    {
        return new self($user);
    }

    /**
     * Ligne d'état du parcours (non enregistrée tant qu'aucune étape n'a été marquée).
     */
    public function onboarding(): Onboarding
    {
        if (! $this->user->onboarding) {
            $this->user->setRelation('onboarding', $this->user->onboarding()->make());
        }

        /** @var Onboarding */
        return $this->user->onboarding;
    }

    public function profilComplet(): bool
    {
        return filled($this->user->name) && filled($this->user->email)
            && filled($this->user->telephone) && filled($this->user->quartier);
    }

    public function serviceTrouve(): bool
    {
        return $this->onboarding()->service_visited_at !== null;
    }

    public function demarcheCommencee(): bool
    {
        return $this->demarcheCommencee ??= $this->user->demarches()->exists();
    }

    /**
     * @return list<array{cle: string, titre: string, texte: string, action: string, url: string, faite: bool}>
     */
    public function etapes(): array
    {
        $serviceId = $this->onboarding()->service_id;

        return [
            [
                'cle' => 'profil',
                'titre' => 'Compléter mon profil',
                'texte' => 'Votre téléphone et votre quartier permettent aux services municipaux de vous recontacter et de mieux vous orienter.',
                'action' => 'Compléter mon profil',
                'url' => route('profile.edit'),
                'faite' => $this->profilComplet(),
            ],
            [
                'cle' => 'service',
                'titre' => 'Trouver un service',
                'texte' => 'Chaque démarche est traitée par un service de la mairie. Ouvrez la fiche de celui qui vous concerne : horaires, adresse et contact.',
                'action' => 'Voir les services',
                'url' => route('services.index'),
                'faite' => $this->serviceTrouve(),
            ],
            [
                'cle' => 'demarche',
                'titre' => 'Commencer une démarche',
                'texte' => 'Déposez votre première demande en ligne, puis suivez son traitement depuis votre espace, sans vous déplacer.',
                'action' => 'Commencer une démarche',
                'url' => route('demarches.create', $serviceId ? ['service' => $serviceId] : []),
                'faite' => $this->demarcheCommencee(),
            ],
        ];
    }

    public function nombreFaites(): int
    {
        return count(array_filter(array_column($this->etapes(), 'faite')));
    }

    /**
     * Numéro (1 à 3) de la première étape non faite, null si tout est fait.
     */
    public function etapeCourante(): ?int
    {
        foreach ($this->etapes() as $index => $etape) {
            if (! $etape['faite']) {
                return $index + 1;
            }
        }

        return null;
    }

    public function pourcentage(): int
    {
        return (int) round($this->nombreFaites() / self::TOTAL_ETAPES * 100);
    }

    public function estTermine(): bool
    {
        return $this->onboarding()->completed_at !== null || $this->nombreFaites() === self::TOTAL_ETAPES;
    }

    public function estPasse(): bool
    {
        return $this->onboarding()->skipped_at !== null;
    }

    /**
     * Le parcours s'impose-t-il encore (redirection après connexion, rappel du tableau de bord) ?
     */
    public function doitAfficher(): bool
    {
        return $this->user->isCitoyen() && ! $this->estPasse() && ! $this->estTermine();
    }

    /**
     * Enregistre la fin du parcours dès que les 3 étapes sont faites.
     */
    public function synchroniser(): void
    {
        $onboarding = $this->onboarding();

        if ($onboarding->completed_at === null && $this->nombreFaites() === self::TOTAL_ETAPES) {
            $onboarding->completed_at = now();
            $onboarding->save();
        }
    }

    public function passer(): void
    {
        $onboarding = $this->onboarding();

        if ($onboarding->skipped_at === null) {
            $onboarding->skipped_at = now();
            $onboarding->save();
        }
    }

    /**
     * Étape 2 : l'habitant a ouvert la fiche d'un service. Sans effet hors parcours en cours.
     */
    public function marquerServiceVisite(Service $service): void
    {
        if (! $this->doitAfficher()) {
            return;
        }

        $onboarding = $this->onboarding();
        $onboarding->service_visited_at ??= now();
        $onboarding->service()->associate($service);
        $onboarding->save();
    }

    /**
     * L'habitant a ouvert le parcours et doit y être ramené après l'étape en cours.
     */
    public function doitRevenirAuParcours(): bool
    {
        return session()->get(self::SESSION_DEPUIS_PARCOURS) === true && $this->doitAfficher();
    }
}
