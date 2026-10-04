<?php

namespace Database\Seeders;

use App\Models\AbonnementLigne;
use App\Models\InterruptionTransport;
use App\Models\LigneTransport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * F97 : ligne 7 interrompue (pont de la Zone artisanale) avec navette, autre ligne, à pied et à vélo,
 * une interruption terminée (invisible) et le trajet habituel de user@example.com sur la ligne 7.
 * Idempotent : rien n'est recréé si une interruption de démo existe déjà.
 */
class InterruptionTransportSeeder extends Seeder
{
    public const CAUSE_DEMO = 'Effondrement partiel du pont de la Zone artisanale : les bus ne peuvent plus traverser. Ligne 7 interrompue entre le Carrefour des Trois-Routes et la Zone industrielle.';

    public function run(): void
    {
        $auteur = User::query()->where('role_id', Role::idFor(Role::ADMIN))->first();
        $ligne7 = LigneTransport::query()->where('numero', '7')->first();
        $ligne1 = LigneTransport::query()->where('numero', '1')->first();

        if ($auteur === null || $ligne7 === null || InterruptionTransport::query()->where('cause', self::CAUSE_DEMO)->exists()) {
            return;
        }

        $interruption = new InterruptionTransport([
            'cause' => self::CAUSE_DEMO,
            'arrets_touches' => "Carrefour des Trois-Routes\nZone artisanale\nZone industrielle",
            'debut' => now()->subHours(2),
            'fin' => now()->addDays(3),
            'solutions' => [
                [
                    'type' => 'navette',
                    'titre' => 'Navette gratuite École Ambohitsoa → Zone industrielle',
                    'description' => 'Descendez du bus 7 à l’arrêt « École primaire Ambohitsoa » : la navette jaune attend de l’autre côté de la rue. Elle passe par le pont de la RN7 et dessert la Zone artisanale puis la Zone industrielle. Votre ticket de bus suffit.',
                    'horaires' => 'Toutes les 15 min, de 5 h à 20 h (10 min de 6 h à 8 h)',
                    'ligne_id' => null,
                    'latitude' => -18.9025,
                    'longitude' => 47.5408,
                ],
                [
                    'type' => 'ligne',
                    'titre' => 'Prendre la ligne 1 jusqu’à la Gare centrale',
                    'description' => 'Pour aller vers le centre-ville, la ligne 1 remplace la ligne 7 depuis la Cité des Fleurs (arrêt Place de l’Indépendance, 5 min à pied).',
                    'horaires' => 'Toutes les 8 min',
                    'ligne_id' => $ligne1?->id,
                    'latitude' => null,
                    'longitude' => null,
                ],
                [
                    'type' => 'pied',
                    'titre' => 'À pied par la passerelle piétonne',
                    'description' => 'La passerelle derrière le marché de la Zone artisanale reste ouverte : 12 minutes à pied entre le Carrefour des Trois-Routes et la Zone industrielle.',
                    'horaires' => 'Ouverte de 5 h à 21 h, éclairée',
                    'ligne_id' => null,
                    'latitude' => -18.8987,
                    'longitude' => 47.5452,
                ],
                [
                    'type' => 'velo',
                    'titre' => 'Vélos en libre-service gratuits',
                    'description' => '20 vélos supplémentaires à la station du Stade municipal, gratuits pendant toute l’interruption avec votre compte habitant.',
                    'horaires' => 'Tous les jours, de 5 h à 22 h',
                    'ligne_id' => null,
                    'latitude' => -18.9071,
                    'longitude' => 47.5317,
                ],
            ],
        ]);
        $interruption->user()->associate($auteur);
        $interruption->notified_at = now()->subHours(2);
        $interruption->save();
        $interruption->lignes()->sync([$ligne7->id]);

        $terminee = new InterruptionTransport([
            'cause' => 'Marché de la fête nationale : rue du Commerce fermée à la circulation.',
            'arrets_touches' => 'Marché couvert',
            'debut' => now()->subDays(5),
            'fin' => now()->subDays(4),
            'solutions' => [],
        ]);
        $terminee->user()->associate($auteur);
        $terminee->notified_at = now()->subDays(5);
        $terminee->save();

        if ($ligne1 !== null) {
            $terminee->lignes()->sync([$ligne1->id]);
        }

        InterruptionTransport::oublierCache();

        $habitant = User::query()->where('email', 'user@example.com')->first();

        if ($habitant !== null && ! AbonnementLigne::query()->whereBelongsTo($habitant)->where('ligne_transport_id', $ligne7->id)->exists()) {
            $abonnement = new AbonnementLigne(['arret' => 'Zone industrielle']);
            $abonnement->user()->associate($habitant);
            $abonnement->ligne()->associate($ligne7);
            $abonnement->save();
        }
    }
}
