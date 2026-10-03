<?php

namespace Database\Seeders;

use App\Models\LigneTransport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Réseau de transports municipaux de Nova Terra. Idempotent : une ligne déjà présente (même numéro) est ignorée.
 */
class LigneTransportSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::query()->where('role_id', Role::idFor(Role::ADMIN))->first() ?? User::query()->first();

        if ($auteur === null) {
            return;
        }

        foreach ($this->lignes() as $data) {
            if (LigneTransport::query()->where('numero', $data['numero'])->exists()) {
                continue;
            }

            $ligne = new LigneTransport($data);
            $ligne->etat = $data['etat'];
            $ligne->perturbation = $data['perturbation'];
            $ligne->user()->associate($auteur);
            $ligne->save();
        }
    }

    /**
     * @return array<int, array{numero: string, nom: string, mode: string, arrets: string, horaires: string, frequence: string, etat: string, perturbation: string|null}>
     */
    private function lignes(): array
    {
        return [
            [
                'numero' => '1',
                'nom' => 'Gare centrale – Université',
                'mode' => 'bus',
                'arrets' => "Gare centrale\nPlace de l'Indépendance\nHôtel de ville\nMarché couvert\nLycée Jean-Moulin\nCité universitaire\nUniversité",
                'horaires' => "Lundi au vendredi : 5 h 30 – 21 h 30\nSamedi : 6 h 00 – 21 h 00\nDimanche et jours fériés : 7 h 00 – 20 h 00",
                'frequence' => 'Toutes les 8 min (15 min le dimanche)',
                'etat' => 'normal',
                'perturbation' => null,
            ],
            [
                'numero' => '4',
                'nom' => 'Port – Hôpital régional',
                'mode' => 'bus',
                'arrets' => "Port de commerce\nAvenue de la Mer\nMarché couvert\nHôtel de ville\nRond-point des Baobabs\nClinique des Lacs\nHôpital régional",
                'horaires' => "Lundi au samedi : 5 h 45 – 20 h 30\nDimanche et jours fériés : 7 h 00 – 19 h 00",
                'frequence' => 'Toutes les 12 min',
                'etat' => 'perturbe',
                'perturbation' => "Travaux avenue de la Mer jusqu'au 10 octobre : l'arrêt « Avenue de la Mer » est déplacé rue des Pêcheurs. Retards de 10 minutes en heure de pointe.",
            ],
            [
                'numero' => '7',
                'nom' => 'Stade municipal – Zone industrielle',
                'mode' => 'bus',
                'arrets' => "Stade municipal\nCité des Fleurs\nÉcole primaire Ambohitsoa\nCarrefour des Trois-Routes\nZone artisanale\nZone industrielle",
                'horaires' => "Lundi au vendredi : 5 h 00 – 20 h 00\nSamedi : 6 h 00 – 14 h 00\nDimanche : pas de service",
                'frequence' => 'Toutes les 15 min (10 min de 6 h à 8 h)',
                'etat' => 'normal',
                'perturbation' => null,
            ],
            [
                'numero' => 'N1',
                'nom' => 'Navette Centre-ville',
                'mode' => 'navette',
                'arrets' => "Hôtel de ville\nMédiathèque\nMarché couvert\nParc des Lacs\nMusée de la Ville\nHôtel de ville",
                'horaires' => "Tous les jours : 8 h 00 – 19 h 00\nGratuite pour les habitants",
                'frequence' => 'Toutes les 10 min',
                'etat' => 'normal',
                'perturbation' => null,
            ],
            [
                'numero' => '12',
                'nom' => 'Gare centrale – Aéroport',
                'mode' => 'taxi-be',
                'arrets' => "Gare centrale\nQuartier Analakely\nRond-point des Baobabs\nVillage artisanal\nAéroport",
                'horaires' => "Tous les jours : 4 h 30 – 22 h 00\nTarif unique : 1 000 Ar",
                'frequence' => 'Départ dès que le véhicule est complet (environ 10 min)',
                'etat' => 'interrompu',
                'perturbation' => "Pont de l'Ikopa fermé pour inspection aujourd'hui de 9 h à 17 h : ligne interrompue. Correspondance conseillée par la ligne 1 puis la navette aéroport depuis l'Université.",
            ],
            [
                'numero' => 'T',
                'nom' => 'Train urbain Nord – Sud',
                'mode' => 'train',
                'arrets' => "Gare Nord\nCité universitaire\nGare centrale\nPort de commerce\nGare Sud",
                'horaires' => "Lundi au vendredi : 5 h 15 – 20 h 45\nSamedi : 6 h 00 – 18 h 00\nDimanche : pas de service",
                'frequence' => 'Toutes les 30 min',
                'etat' => 'normal',
                'perturbation' => null,
            ],
        ];
    }
}
