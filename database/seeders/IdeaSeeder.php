<?php

namespace Database\Seeders;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Boîte à idées de Nova Terra (F68). Idempotent (clé : titre).
 *
 * Six idées à des états différents, avec des soutiens variés et deux réponses de la ville.
 * La première est proposée par user@example.com pour la démonstration du suivi.
 */
class IdeaSeeder extends Seeder
{
    public function run(): void
    {
        $citoyens = User::query()->get()->filter(fn (User $user): bool => $user->isCitoyen())->values();
        $agent = User::where('email', 'agent@example.com')->first();
        $demo = User::where('email', 'user@example.com')->first() ?? $citoyens->first();

        if ($demo === null || $citoyens->isEmpty()) {
            return;
        }

        $exemples = [
            [
                'title' => 'Des bancs ombragés près du marché', 'category' => 'cadre_de_vie', 'status' => 'retenue', 'soutiens' => 9, 'jours' => 12,
                'description' => "Il fait très chaud en milieu de journée autour du marché central.\nQuelques bancs sous des arbres ou des voiles d’ombrage permettraient aux aînés et aux familles de se reposer.",
                'response' => "Merci pour cette idée soutenue par de nombreux habitants.\nSix bancs ombragés seront installés autour du marché central d’ici la fin du mois prochain.",
            ],
            [
                'title' => 'Un composteur collectif par quartier', 'category' => 'environnement', 'status' => 'a_l_etude', 'soutiens' => 7, 'jours' => 6,
                'description' => 'Beaucoup de déchets de cuisine pourraient devenir du compost pour les jardins partagés. Un bac par quartier, géré par des volontaires, réduirait les ordures.',
            ],
            [
                'title' => 'Une navette le dimanche', 'category' => 'mobilite', 'status' => 'recue', 'soutiens' => 5, 'jours' => 2,
                'description' => 'Le dimanche, aucun transport ne relie les quartiers périphériques au centre. Une navette toutes les heures aiderait à aller voir sa famille ou au marché.',
            ],
            [
                'title' => 'Un atelier de réparation de vélos', 'category' => 'culture_loisirs', 'status' => 'recue', 'soutiens' => 3, 'jours' => 1,
                'description' => 'Un atelier ouvert le samedi, avec des outils partagés et des bénévoles, pour apprendre à réparer son vélo au lieu de le jeter.',
            ],
            [
                'title' => 'Plus de bornes d’eau potable', 'category' => 'services_publics', 'status' => 'a_l_etude', 'soutiens' => 6, 'jours' => 8,
                'description' => 'Il n’y a qu’une borne d’eau potable sur la grande place. En ajouter près des écoles et des terrains de sport éviterait de longues files.',
            ],
            [
                'title' => 'Des ateliers numériques pour les aînés', 'category' => 'solidarite', 'status' => 'non_retenue', 'soutiens' => 2, 'jours' => 15,
                'description' => 'Beaucoup de personnes âgées ne savent pas utiliser le portail de la ville. Des ateliers gratuits les aideraient à faire leurs démarches seules.',
                'response' => "Merci pour cette belle idée.\nLa médiathèque propose déjà des ateliers numériques gratuits chaque mercredi : nous allons mieux les faire connaître plutôt que d’en créer de nouveaux.",
            ],
        ];

        foreach ($exemples as $index => $exemple) {
            if (Idea::where('title', $exemple['title'])->exists()) {
                continue;
            }

            $auteur = $index === 0 ? $demo : $citoyens[$index % $citoyens->count()];
            $date = now()->subDays($exemple['jours']);

            $idea = new Idea(['title' => $exemple['title'], 'description' => $exemple['description'], 'category' => $exemple['category']]);
            $idea->user()->associate($auteur);
            $idea->status = $exemple['status'];
            $idea->created_at = $date;
            $idea->status_changed_at = $exemple['status'] === 'recue' ? null : $date->copy()->addDays(1);

            if (isset($exemple['response'])) {
                $idea->response = $exemple['response'];
                $idea->responded_at = $date->copy()->addDays(2);
                $idea->respondedBy()->associate($agent);
            }

            $idea->save();
            $idea->reference = Idea::referencePour($idea);
            $idea->save();

            // Soutiens d'autres habitants (jamais l'auteur), une seule fois chacun : mécanisme F52.
            $citoyens->reject(fn (User $user): bool => $user->id === $auteur->id)
                ->take($exemple['soutiens'])
                ->each(fn (User $user) => $idea->ajouterSoutien($user));
        }
    }
}
