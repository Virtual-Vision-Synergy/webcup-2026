<?php

namespace Database\Seeders;

use App\Models\Demarche;
use App\Models\ReponseDemarche;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Démo F84 : fils d'échanges sur les démarches de user@example.com (hors production, comptes au mot de passe connu).
 *   - 1re démarche : réponse d'un agent (badge « Réponse envoyée »)
 *   - 2e démarche  : réponse d'un agent puis relance de l'habitant (« En attente de réponse »)
 *   - les autres   : aucune réponse (filtre « Sans réponse uniquement »)
 * Écrit directement les messages, sans notification (pas d'e-mail pendant le seed). Idempotent.
 */
class ReponseDemarcheSeeder extends Seeder
{
    public function run(): void
    {
        $citoyen = User::where('email', 'user@example.com')->first();
        $agent = User::where('email', 'agent@example.com')->first() ?? User::where('email', 'admin@example.com')->first();

        if (! $citoyen || ! $agent) {
            return;
        }

        $demarches = Demarche::query()->whereBelongsTo($citoyen)->oldest('id')->take(2)->get();

        if ($demarches->isEmpty() || ReponseDemarche::query()->whereIn('demarche_id', $demarches->modelKeys())->exists()) {
            return;
        }

        $this->ecrire($demarches[0], $agent, true, ReponseDemarche::MODELES['accuse']['contenu'], 26);

        if (isset($demarches[1])) {
            $this->ecrire($demarches[1], $agent, true, ReponseDemarche::MODELES['piece']['contenu'], 30);
            $this->ecrire($demarches[1], $citoyen, false, "Bonjour,\n\nJe peux passer déposer le justificatif de domicile jeudi matin au guichet. Est-ce suffisant ?\n\nMerci.", 4);
        }
    }

    private function ecrire(Demarche $demarche, User $auteur, bool $deAgent, string $message, int $ilYaHeures): void
    {
        $reponse = new ReponseDemarche(['message' => $message]);
        $reponse->demarche()->associate($demarche);
        $reponse->user()->associate($auteur);
        $reponse->de_agent = $deAgent;
        $reponse->created_at = now()->subHours($ilYaHeures);
        $reponse->updated_at = $reponse->created_at;
        $reponse->save();
    }
}
