<?php

namespace Database\Seeders;

use App\Models\Remontee;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Remontées sur les données (F51). Idempotent (clé : auteur + objet).
 *
 * user@example.com a trois remontées : Reçue, Prise en compte, Répondue (traitées par agent@example.com).
 */
class RemonteeSeeder extends Seeder
{
    public function run(): void
    {
        $citoyen = User::where('email', 'user@example.com')->first();
        $agent = User::where('email', 'agent@example.com')->first();

        if ($citoyen === null || $agent === null) {
            return;
        }

        $exemples = [
            [
                'categorie' => 'comprendre', 'objet' => 'À quoi sert mon numéro de téléphone ?',
                'message' => "J’ai donné mon numéro à l’inscription.\nJe ne sais pas qui peut le voir ni pourquoi la mairie en a besoin.",
                'statut' => 'recue', 'envoyee' => now()->subHours(5),
            ],
            [
                'categorie' => 'corriger', 'objet' => 'Je reçois les alertes d’un ancien quartier',
                'message' => 'J’ai déménagé à Ambohimanarina, mais je reçois encore les alertes de mon ancien quartier. Pouvez-vous vérifier ?',
                'statut' => 'prise_en_compte', 'envoyee' => now()->subDays(2),
            ],
            [
                'categorie' => 'supprimer', 'objet' => 'Que devient mon historique si je supprime mon compte ?',
                'message' => 'Si je supprime mon compte, mes anciennes démarches sont-elles vraiment effacées ?',
                'statut' => 'repondue', 'envoyee' => now()->subDays(5),
                'reponse' => "Bonjour,\nOui : vos démarches, signalements, rendez-vous et messages sont effacés avec votre compte. Vos remontées sont gardées sans votre nom ni votre e-mail.\nLe détail est sur la page « Vos données ».",
            ],
        ];

        foreach ($exemples as $exemple) {
            $remontee = Remontee::firstOrNew(['user_id' => $citoyen->id, 'objet' => $exemple['objet']]);
            $remontee->fill(['categorie' => $exemple['categorie'], 'message' => $exemple['message']]);
            $remontee->user()->associate($citoyen);
            $remontee->statut = $exemple['statut'];
            $remontee->envoyee_le = $exemple['envoyee'];

            if ($exemple['statut'] !== 'recue') {
                $remontee->prise_en_compte_le = $exemple['envoyee']->copy()->addHours(3);
                $remontee->agentPriseEnCharge()->associate($agent);
            }

            if (isset($exemple['reponse'])) {
                $remontee->reponse = $exemple['reponse'];
                $remontee->repondue_le = $exemple['envoyee']->copy()->addDay();
                $remontee->agentReponse()->associate($agent);
            }

            $remontee->save();

            if ($remontee->reference === null) {
                $remontee->reference = sprintf('%s-%s-%06d', Remontee::PREFIXE_REFERENCE, $exemple['envoyee']->format('Y'), $remontee->id);
                $remontee->save();
            }
        }
    }
}
