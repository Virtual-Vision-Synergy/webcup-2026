<?php

namespace Database\Seeders;

use App\Models\Annonce;
use App\Models\Role;
use App\Models\User;
use App\Services\NotifierAnnonce;
use Illuminate\Database\Seeder;

/**
 * Alerte de démo F104 « Tempête solaire », toute la ville, construite à partir du modèle Annonce::MODELES.
 * En production, elle est publiée par la migration add_alerte_tempete_solaire_to_annonces_table.
 */
class AlerteTempeteSolaireSeeder extends Seeder
{
    /** Durée de la démo : l'alerte reste visible pendant toute la période d'évaluation. */
    public const DUREE_HEURES = 72;

    public function run(): void
    {
        $alerte = self::publierAlerte();

        // Hors production : cloche (et e-mail en local) pour tous les habitants, une seule fois.
        if ($alerte !== null && ! app()->isProduction()) {
            app(NotifierAnnonce::class)->notifierSiVisible($alerte);
        }
    }

    /**
     * Crée l'alerte si elle n'existe pas déjà (même titre) ; sinon ne fait rien. Sans agent ni admin : rien.
     */
    public static function publierAlerte(): ?Annonce
    {
        $champs = Annonce::depuisModele('tempete-solaire', now(), self::DUREE_HEURES);
        $existante = Annonce::query()->where('titre', $champs['titre'] ?? '')->first();
        $auteur = User::query()
            ->whereIn('role_id', [Role::idFor(Role::AGENT), Role::idFor(Role::ADMIN)])
            ->orderBy('id')
            ->first();

        if ($champs === null || $existante !== null || $auteur === null) {
            return $existante;
        }

        $alerte = new Annonce([
            ...$champs,
            'debut' => $champs['debut']->utc(),
            'fin' => $champs['fin']->utc(),
            'impact_prevu_le' => ($champs['impact_prevu_le'] ?? null)?->utc(),
        ]);
        $alerte->user()->associate($auteur);
        $alerte->save();

        return $alerte;
    }
}
