<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Onboarding;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Démo de « Mes demandes » (D11), idempotente (relançable sans doublon) :
 *   - user@example.com            4 signalements aux états variés, étapes étalées sur plusieurs jours
 *   - voisin@example.com          un autre citoyen avec ses propres demandes (démonstration du 403)
 *   - sans.demande@example.com    un citoyen sans aucune demande (état vide)
 * Les étapes sont écrites dans le journal d'audit (F47), là où l'application les enregistre réellement.
 * Hors production uniquement (comptes au mot de passe connu).
 */
class MesDemandesSeeder extends Seeder
{
    public function run(): void
    {
        $agent = User::where('email', 'agent@example.com')->first();
        $citoyen = User::where('email', 'user@example.com')->first();

        if (! $agent || ! $citoyen) {
            return;
        }

        // [catégorie, description, lieu, [statut => il y a N jours (et heures)]] : la première étape est le dépôt.
        $demandes = [
            ['eclairage', 'Le lampadaire au croisement de la rue des Pionniers est cassé : le trottoir est plongé dans le noir chaque soir.', 'Rue des Pionniers, croisement avec l’allée des Serres', ['nouveau' => [6, 19], 'en_cours' => [4, 10]]],
            ['voirie', 'Un nid-de-poule profond s’est formé sur la voie de droite, les deux-roues font des écarts dangereux.', 'Avenue du Dôme, à hauteur du n° 40', ['nouveau' => [10, 8], 'en_cours' => [8, 14], 'resolu' => [3, 11]]],
            ['proprete', 'Dépôt sauvage de cartons et d’encombrants derrière les étals, les sacs s’éventrent avec le vent.', 'Près du marché couvert, côté parking', ['nouveau' => [1, 9]]],
            ['eau', 'Fuite d’eau sur la canalisation du trottoir : l’eau coule en continu vers la chaussée depuis hier.', 'Quartier sud, rue des Citernes', ['nouveau' => [2, 7], 'en_cours' => [1, 15]]],
        ];
        $this->creerDemandes($citoyen, $agent, $demandes);

        $voisin = $this->citoyen('voisin@example.com', 'Rija Voisin');
        $this->creerDemandes($voisin, $agent, [
            ['mobilier', 'Le banc de l’abribus est arraché et présente des vis saillantes.', 'Boulevard des Pionniers, arrêt Terminus', ['nouveau' => [5, 10], 'en_cours' => [3, 9]]],
            ['espaces_verts', 'Une grosse branche est tombée dans le square et bloque l’allée principale.', 'Square du Dôme, entrée nord', ['nouveau' => [2, 16]]],
        ]);

        $this->citoyen('sans.demande@example.com', 'Nirina Sansdemande');
    }

    private function citoyen(string $email, string $name): User
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $user = User::factory()->citoyen()->profilComplet()->create(['name' => $name, 'email' => $email]);
        Onboarding::factory()->termine()->for($user)->create();

        return $user;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: array<string, array{0: int, 1: int}>}>  $demandes
     */
    private function creerDemandes(User $auteur, User $agent, array $demandes): void
    {
        foreach ($demandes as [$categorie, $description, $lieu, $etapes]) {
            if (Signalement::duCitoyen($auteur)->where('lieu', $lieu)->exists()) {
                continue;
            }

            $dates = array_map(fn (array $quand): Carbon => now()->subDays($quand[0])->setTime($quand[1], random_int(0, 59)), $etapes);
            $statutActuel = (string) array_key_last($etapes);

            $signalement = new Signalement(['categorie' => $categorie, 'description' => $description, 'lieu' => $lieu]);
            $signalement->user()->associate($auteur);
            $signalement->statut = $statutActuel;
            $signalement->created_at = $dates['nouveau'];
            $signalement->updated_at = $dates[$statutActuel];
            $signalement->save();

            $precedent = 'nouveau';
            foreach (array_slice($dates, 1, null, true) as $statut => $date) {
                AuditLog::factory()->par($agent)->action('status_changed')->create([
                    'subject_type' => class_basename($signalement),
                    'subject_id' => $signalement->id,
                    'subject_label' => 'Signalement : '.$signalement->auditLabel(),
                    'changes' => ['statut' => ['avant' => Signalement::STATUT_LABELS[$precedent], 'apres' => Signalement::STATUT_LABELS[$statut]]],
                    'created_at' => $date,
                ]);
                $precedent = $statut;
            }
        }
    }
}
