<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * « Par où commencer ? » (F72) : 3 à 5 services recommandés à un nouvel habitant selon sa situation
 * (logement, famille, emploi, santé), sans refaire l'inscription ni le parcours de prise en main (D12).
 * Les réponses sont enregistrées dans sa ligne de parcours (onboardings.situation) et restent modifiables.
 */
class ParOuCommencer
{
    /**
     * Questions posées à l'habitant : libellé, aide et catégories de services concernées (par ordre d'utilité).
     *
     * @var array<string, array{label: string, aide: string, categories: list<string>}>
     */
    public const SITUATIONS = [
        'logement' => [
            'label' => 'Je m’installe dans un nouveau logement',
            'aide' => 'Déclarer votre arrivée, raccorder votre logement, connaître les règles du quartier.',
            'categories' => ['administratif', 'urbanisme'],
        ],
        'famille' => [
            'label' => 'J’ai des enfants',
            'aide' => 'Inscription à l’école, garde, activités jeunesse.',
            'categories' => ['education', 'social'],
        ],
        'emploi' => [
            'label' => 'Je cherche un emploi ou je lance mon activité',
            'aide' => 'Aides sociales, commerce et marchés de la ville.',
            'categories' => ['social', 'economie'],
        ],
        'sante' => [
            'label' => 'J’ai besoin d’un médecin ou d’un suivi de santé',
            'aide' => 'Santé publique, hôpitaux et centres de soins.',
            'categories' => ['sante', 'social'],
        ],
    ];

    /** Catégorie utile à tout nouvel arrivant (état civil, accueil de la mairie). */
    public const CATEGORIE_DE_BASE = 'administratif';

    public const MINIMUM = 3;

    public const MAXIMUM = 5;

    public function __construct(public readonly User $user) {}

    public static function pour(User $user): self
    {
        return new self($user);
    }

    /**
     * Situation enregistrée (null tant que l'habitant n'a pas répondu, tableau vide s'il n'a rien coché).
     *
     * @return list<string>|null
     */
    public function situation(): ?array
    {
        $situation = OnboardingProgress::pour($this->user)->onboarding()->situation;

        return $situation === null ? null : array_values(array_intersect($situation, array_keys(self::SITUATIONS)));
    }

    public function aRepondu(): bool
    {
        return $this->situation() !== null;
    }

    /**
     * Enregistre les réponses (clés déjà validées par le composant ; filtrées ici par sécurité).
     *
     * @param  array<int, string>  $situations
     */
    public function enregistrer(array $situations): void
    {
        $onboarding = OnboardingProgress::pour($this->user)->onboarding();
        $onboarding->situation = array_values(array_intersect(array_keys(self::SITUATIONS), $situations));
        $onboarding->save();
    }

    /**
     * L'habitant a commencé ses premières démarches : le bloc du tableau de bord se replie.
     */
    public function demarcheCommencee(): bool
    {
        return $this->user->demarches()->exists();
    }

    /**
     * 3 à 5 services disponibles, en alternant les catégories de la situation (une à tour de rôle),
     * complétés par les services mis en avant si la situation n'en fournit pas assez.
     *
     * @return Collection<int, Service>
     */
    public function recommandations(): Collection
    {
        $categories = $this->categories();

        $parCategorie = Service::query()->disponibles()->whereIn('categorie', $categories)->prioritaires()->get()
            ->groupBy('categorie');

        /** @var Collection<int, Service> $choisis */
        $choisis = collect();
        $rangMax = $parCategorie->map(fn (Collection $services): int => $services->count())->max() ?? 0;

        for ($rang = 0; $rang < $rangMax; $rang++) {
            foreach ($categories as $categorie) {
                $service = $parCategorie->get($categorie)?->get($rang);

                if ($service !== null && $choisis->count() < self::MAXIMUM) {
                    $choisis->push($service);
                }
            }
        }

        if ($choisis->count() < self::MINIMUM) {
            $complements = Service::query()->disponibles()
                ->whereKeyNot($choisis->pluck('id')->all())
                ->prioritaires()
                ->limit(self::MINIMUM - $choisis->count())
                ->get();
            $choisis = $choisis->concat($complements);
        }

        return $choisis->values();
    }

    /**
     * Pourquoi ce service est proposé : « Pour : J'ai des enfants ».
     */
    public function raison(Service $service): string
    {
        foreach ($this->situation() ?? [] as $cle) {
            if (in_array($service->categorie, self::SITUATIONS[$cle]['categories'], true)) {
                return 'Pour : '.self::SITUATIONS[$cle]['label'];
            }
        }

        return 'Utile à tout nouvel habitant';
    }

    /**
     * Catégories à proposer, sans doublon : celles de la situation, puis la catégorie de base.
     *
     * @return list<string>
     */
    private function categories(): array
    {
        $categories = [];
        foreach ($this->situation() ?? [] as $cle) {
            array_push($categories, ...self::SITUATIONS[$cle]['categories']);
        }
        $categories[] = self::CATEGORIE_DE_BASE;

        return array_values(array_unique($categories));
    }
}
