<?php

namespace App\Support;

use App\Models\Annonce;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

use function Illuminate\Support\defer;

/**
 * F94 : page « Infos essentielles » qui reste servie pendant un incident de la plateforme.
 *
 * - La consigne d'incident est publiée par un admin (page Filament « Infos essentielles ») et affichée
 *   en bandeau en haut de toutes les pages.
 * - La page est une version statique (HTML simple, sans JS ni image) régénérée à chaque modification
 *   de la consigne, d'un service ou d'une interruption : elle est lue depuis un fichier, sans base de données.
 * - F104 : les alertes graves en cours (ex. tempête solaire) y sont reprises avec leurs consignes, pour rester
 *   lisibles hors ligne (F93) pendant la perturbation ; la page est régénérée à chaque modification d'une annonce.
 * - Si le fichier manque et que la base est coupée, une version de secours (numéros, mairie) est rendue.
 */
class InfosEssentielles
{
    public const CACHE_CONSIGNE = 'infos_essentielles.consigne';

    /** Fichier statique (disque « local », hors du dossier public). */
    public const FICHIER = 'infos-essentielles/index.html';

    public const NIVEAU_OPTIONS = ['information', 'alerte'];

    public const NIVEAU_LABELS = [
        'information' => 'Information',
        'alerte' => 'Alerte',
    ];

    /** Coordonnées de la mairie, sans base de données : toujours affichables. */
    public const MAIRIE = [
        'nom' => 'Mairie de Nova Terra — Hôtel de ville',
        'adresse' => "Place de l'Indépendance, Nova Terra",
        'telephone' => '+261 20 22 400 00',
        'email' => 'contact@mairie-novaterra.mg',
    ];

    /** @var array<int, array{jours: string, heures: string}> */
    public const HORAIRES_MAIRIE = [
        ['jours' => 'Lundi au vendredi', 'heures' => '8 h 00 – 16 h 30'],
        ['jours' => 'Samedi', 'heures' => '8 h 00 – 12 h 00'],
        ['jours' => 'Dimanche et jours fériés', 'heures' => 'Fermé (numéros d’urgence joignables 24 h/24)'],
    ];

    /**
     * Consigne d'incident en cours, ou null. Ne lève jamais d'erreur (cache en base indisponible → pas de bandeau).
     *
     * @return array{titre: string, message: string, niveau: string, publiee_le: string}|null
     */
    public static function consigne(): ?array
    {
        try {
            $consigne = Cache::memo()->get(self::CACHE_CONSIGNE);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($consigne) || ! isset($consigne['titre'], $consigne['message'], $consigne['niveau'], $consigne['publiee_le'])) {
            return null;
        }

        return [
            'titre' => (string) $consigne['titre'],
            'message' => (string) $consigne['message'],
            'niveau' => (string) $consigne['niveau'],
            'publiee_le' => (string) $consigne['publiee_le'],
        ];
    }

    public static function publier(string $titre, string $message, string $niveau): void
    {
        Cache::memo()->forever(self::CACHE_CONSIGNE, [
            'titre' => trim($titre),
            'message' => trim($message),
            'niveau' => in_array($niveau, self::NIVEAU_OPTIONS, true) ? $niveau : 'information',
            'publiee_le' => now()->toIso8601String(),
        ]);

        self::regenerer();
    }

    public static function retirer(): void
    {
        Cache::memo()->forget(self::CACHE_CONSIGNE);

        self::regenerer();
    }

    /**
     * Régénère la version statique après la réponse (une seule fois même si plusieurs modèles changent).
     */
    public static function programmerRegeneration(): void
    {
        defer(fn () => self::regenerer(), 'infos-essentielles');
    }

    /**
     * Écrit la version statique à partir des données à jour. Ne fait jamais échouer l'action qui l'a déclenchée.
     */
    public static function regenerer(): bool
    {
        try {
            Storage::disk('local')->put(self::FICHIER, self::rendre(self::servicesNonDisponibles(), self::alertesEnCours()));

            return true;
        } catch (Throwable $e) {
            Log::warning('F94 : régénération des infos essentielles impossible.', ['erreur' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * HTML de la page : fichier statique si présent, sinon génération, sinon version de secours sans base.
     */
    public static function html(): string
    {
        try {
            $disque = Storage::disk('local');

            if ($disque->exists(self::FICHIER)) {
                return (string) $disque->get(self::FICHIER);
            }
        } catch (Throwable) {
            // Disque illisible : on tente la génération ci-dessous.
        }

        try {
            $services = self::servicesNonDisponibles();
            $alertes = self::alertesEnCours();
        } catch (Throwable) {
            return self::rendre(null);
        }

        $html = self::rendre($services, $alertes);

        try {
            Storage::disk('local')->put(self::FICHIER, $html);
        } catch (Throwable) {
            // Fichier non écrit : la page reste servie, elle sera écrite à la prochaine modification.
        }

        return $html;
    }

    /**
     * @param  Collection<int, Service>|null  $services  null = état des services inconnu (base indisponible)
     * @param  Collection<int, Annonce>|null  $alertes  alertes graves en cours (F104) ; null = inconnues (base indisponible)
     */
    public static function rendre(?Collection $services, ?Collection $alertes = null): string
    {
        $genereeLe = Carbon::now((string) config('rendez_vous.fuseau', 'UTC'));
        $genereeLe->locale('fr');

        return view('infos-essentielles', [
            'consigne' => self::consigne(),
            'services' => $services,
            'alertes' => $alertes,
            'genereeLe' => $genereeLe->translatedFormat('l j F Y à H:i'),
        ])->render();
    }

    /**
     * F104 : alertes graves (Alerte, Danger) en cours de diffusion, de la plus grave à la moins grave.
     *
     * @return Collection<int, Annonce>
     */
    private static function alertesEnCours(): Collection
    {
        return Annonce::query()
            ->active()
            ->whereIn('niveau', Annonce::NIVEAUX_GRAVES)
            ->with('quartier:id,nom')
            ->latest('debut')
            ->get()
            ->sortByDesc(fn (Annonce $annonce): int => $annonce->gravite())
            ->values();
    }

    /**
     * @return Collection<int, Service>
     */
    private static function servicesNonDisponibles(): Collection
    {
        return Service::query()
            ->with('interruptionCourante')
            ->orderBy('nom')
            ->get()
            ->reject(fn (Service $service): bool => $service->etat() === Service::ETAT_DISPONIBLE)
            ->values();
    }
}
