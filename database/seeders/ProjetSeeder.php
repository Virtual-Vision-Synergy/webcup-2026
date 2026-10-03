<?php

namespace Database\Seeders;

use App\Models\Projet;
use App\Models\Quartier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjetSeeder extends Seeder
{
    /**
     * Projets de la ville (F67) : 5 projets crédibles pour Nova Terra (voirie, école, parc, réseau d'eau, énergie).
     */
    public function run(): void
    {
        $agent = User::where('role_id', Role::idFor(Role::AGENT))->first()
            ?? User::where('role_id', Role::idFor(Role::ADMIN))->first();

        if ($agent === null) {
            return;
        }

        $projets = [
            [
                'titre' => 'Réfection de l’avenue des Pionniers',
                'quartier' => 'centre',
                'etat' => 'en_cours',
                'date_debut' => '2026-08-01',
                'date_fin' => '2027-01-31',
                'budget' => 850000000,
                'description' => "L’avenue des Pionniers est abîmée et dangereuse pour les piétons. La ville refait la chaussée, élargit les trottoirs et ajoute des passages piétons éclairés.\n\nPendant les travaux, la circulation se fait sur une seule voie. Les bus de la ligne 2 sont déviés par la rue du Marché.",
                'etapes' => "Concertation avec les riverains\nRéparation des canalisations sous la chaussée\nNouvelle chaussée et trottoirs\nMarquage, éclairage et réouverture complète",
                'etapes_terminees' => 2,
                'latitude' => -18.9105,
                'longitude' => 47.5251,
            ],
            [
                'titre' => 'Construction de l’école primaire du Nord',
                'quartier' => 'nord',
                'etat' => 'en_cours',
                'date_debut' => '2026-03-15',
                'date_fin' => '2027-08-31',
                'budget' => 2400000000,
                'description' => "Les classes du quartier Nord sont surchargées. Une nouvelle école de 12 classes, avec cantine et cour ombragée, accueillera 360 élèves.\n\nL’ouverture est prévue pour la rentrée scolaire 2027. Les inscriptions se feront à la mairie annexe du Nord.",
                'etapes' => "Choix du terrain et plans\nFondations\nConstruction des bâtiments\nÉquipement des classes et de la cantine\nOuverture aux élèves",
                'etapes_terminees' => 2,
                'latitude' => -18.8932,
                'longitude' => 47.5208,
            ],
            [
                'titre' => 'Parc des Familles au bord du lac',
                'quartier' => 'ouest',
                'etat' => 'a_l_etude',
                'date_debut' => '2027-02-01',
                'date_fin' => '2027-12-15',
                'budget' => null,
                'description' => "La ville souhaite transformer le terrain vague au bord du lac en parc public : aires de jeux, bancs, arbres et chemin de promenade accessible aux poussettes et aux fauteuils roulants.\n\nLe projet est à l’étude : vos idées sont les bienvenues lors des réunions de quartier.",
                'etapes' => "Réunions avec les habitants\nPlans du parc\nVote du budget\nPlantations et aménagements\nOuverture du parc",
                'etapes_terminees' => 1,
                'latitude' => -18.9150,
                'longitude' => 47.5050,
            ],
            [
                'titre' => 'Rénovation du réseau d’eau potable du Sud',
                'quartier' => 'sud',
                'etat' => 'en_cours',
                'date_debut' => '2026-06-01',
                'date_fin' => '2026-12-20',
                'budget' => 1200000000,
                'description' => "Les tuyaux du quartier Sud sont anciens : fuites et coupures sont fréquentes. La ville les remplace rue par rue pour garantir une eau propre et une pression régulière.\n\nDes coupures courtes sont annoncées 48 h à l’avance sur cette plateforme. Un camion-citerne est présent pendant chaque coupure.",
                'etapes' => "Repérage des fuites\nRemplacement des conduites principales\nRaccordement des maisons\nContrôle de la qualité de l’eau",
                'etapes_terminees' => 1,
                'latitude' => -18.9300,
                'longitude' => 47.5280,
            ],
            [
                'titre' => 'Panneaux solaires sur les bâtiments publics',
                'quartier' => 'est',
                'etat' => 'termine',
                'date_debut' => '2025-11-01',
                'date_fin' => '2026-07-15',
                'budget' => 640000000,
                'description' => "La mairie annexe de l’Est, le centre de santé et la médiathèque produisent désormais leur propre électricité grâce à des panneaux solaires.\n\nCes bâtiments restent ouverts pendant les délestages et la facture d’électricité de la ville baisse d’environ un tiers.",
                'etapes' => "Étude des toitures\nInstallation des panneaux\nRaccordement et batteries\nMise en service",
                'etapes_terminees' => 4,
                'latitude' => -18.9080,
                'longitude' => 47.5400,
            ],
        ];

        foreach ($projets as $donnees) {
            $quartier = $donnees['quartier'];
            unset($donnees['quartier']);

            $projet = Projet::firstOrNew(['titre' => $donnees['titre']]);
            $projet->fill($donnees);
            $projet->quartier_id = Quartier::idPour($quartier);
            $projet->user()->associate($agent);
            $projet->save();
        }
    }
}
