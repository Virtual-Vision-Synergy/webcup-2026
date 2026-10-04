<?php

namespace Database\Seeders;

use App\Models\AvisProjet;
use App\Models\Projet;
use App\Models\Quartier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Projets de la ville de Nova Terra (F67). Idempotent : un projet déjà présent (même titre) est ignoré.
 */
class ProjetSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::query()->where('role_id', Role::idFor(Role::AGENT))->first()
            ?? User::query()->where('role_id', Role::idFor(Role::ADMIN))->first()
            ?? User::query()->first();

        if ($auteur === null) {
            return;
        }

        foreach ($this->projets() as $data) {
            if (Projet::query()->where('titre', $data['titre'])->exists()) {
                continue;
            }

            $quartier = $data['quartier'];
            unset($data['quartier']);

            $projet = new Projet($data);
            $projet->quartier_id = $quartier === null ? null : Quartier::idPour($quartier);
            $projet->user()->associate($auteur);
            $projet->save();
        }

        // F66 : les projets encore à l'étude sont ouverts à l'avis des habitants pour la démo.
        Projet::query()->where('etat', 'etude')->update(['consultation_ouverte' => true]);
        $this->avisDeDemo();
    }

    /**
     * Quelques avis d'habitants (hors compte de démo user@example.com, pour qu'il puisse tester lui-même).
     */
    private function avisDeDemo(): void
    {
        $projet = Projet::query()->where('consultation_ouverte', true)->first();

        if ($projet === null) {
            return;
        }

        $reponses = [
            ['pour', 'Enfin ! Le quartier en a vraiment besoin.'],
            ['pour', null],
            ['contre', 'J\'ai peur du bruit pendant les travaux, pensez aux horaires.'],
            ['sans_avis', 'Il faudrait plus d\'informations sur le budget.'],
            ['pour', 'Pensez aux personnes à mobilité réduite.'],
        ];

        $habitants = User::query()->citizens()->where('email', '!=', 'user@example.com')->limit(count($reponses))->get();

        foreach ($habitants as $i => $habitant) {
            if (AvisProjet::query()->whereBelongsTo($habitant)->whereBelongsTo($projet)->exists()) {
                continue;
            }

            [$position, $commentaire] = $reponses[$i];
            $avis = new AvisProjet(['position' => $position, 'commentaire' => $commentaire]);
            $avis->user()->associate($habitant);
            $avis->projet()->associate($projet);
            $avis->save();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projets(): array
    {
        return [
            [
                'titre' => 'Réfection de l\'avenue de l\'Indépendance',
                'categorie' => 'voirie',
                'quartier' => 'centre',
                'resume' => 'Une avenue refaite à neuf, avec des trottoirs larges et une piste cyclable.',
                'description' => "L'avenue de l'Indépendance est abîmée : nids-de-poule, trottoirs étroits, eau qui stagne quand il pleut.\n\nNous refaisons toute la chaussée, nous élargissons les trottoirs pour les piétons et les poussettes, et nous ajoutons une piste cyclable séparée des voitures. Les caniveaux sont remplacés pour que l'eau s'évacue mieux.\n\nPendant les travaux, la circulation se fait sur une seule voie. Les commerces restent ouverts.",
                'etat' => 'en_cours',
                'etapes' => "Concertation avec les riverains et les commerçants\nChoix de l'entreprise\nRemplacement des caniveaux\nNouvelle chaussée et piste cyclable\nTrottoirs, éclairage et plantations",
                'etapes_terminees' => 3,
                'avancement' => 60,
                'date_debut' => '2026-03-02',
                'date_fin' => '2027-01-31',
                'budget' => 1_850_000_000,
                'lieu' => 'Avenue de l\'Indépendance, de la gare à l\'hôtel de ville',
                'latitude' => -18.9087,
                'longitude' => 47.5245,
            ],
            [
                'titre' => 'Nouvelle école primaire des Lacs',
                'categorie' => 'ecole',
                'quartier' => 'nord',
                'resume' => 'Une école de 12 classes pour accueillir 360 enfants près de chez eux.',
                'description' => "Les écoles du quartier Nord sont pleines et beaucoup d'enfants font un long trajet chaque matin.\n\nLa nouvelle école aura 12 classes, une cantine, une cour ombragée et une bibliothèque ouverte aux habitants le samedi. Le bâtiment est bien ventilé et alimenté par des panneaux solaires.\n\nLes inscriptions ouvriront à la mairie trois mois avant la rentrée.",
                'etat' => 'en_cours',
                'etapes' => "Achat du terrain\nPlans validés avec les parents d'élèves\nConstruction du bâtiment\nÉquipement des classes\nOuverture aux élèves",
                'etapes_terminees' => 2,
                'avancement' => 45,
                'date_debut' => '2026-01-15',
                'date_fin' => '2027-09-01',
                'budget' => 3_200_000_000,
                'lieu' => 'Rue des Lacs, à côté du terrain de sport',
                'latitude' => -18.8985,
                'longitude' => 47.5281,
            ],
            [
                'titre' => 'Parc urbain du Bord-de-l\'eau',
                'categorie' => 'parc',
                'quartier' => 'ouest',
                'resume' => 'Un grand parc gratuit avec aires de jeux, chemins de promenade et jardins partagés.',
                'description' => "Un terrain vague au bord du lac devient un parc ouvert à tous, tous les jours de 6 h à 20 h.\n\nIl y aura des aires de jeux pour les petits et les grands, des bancs à l'ombre, un parcours sportif, des toilettes publiques et des jardins partagés que les habitants pourront cultiver.\n\nLes habitants ont voté le plan du parc lors de la consultation de l'an dernier.",
                'etat' => 'termine',
                'etapes' => "Consultation des habitants\nNettoyage du terrain\nPlantations et chemins\nAires de jeux et équipements\nOuverture au public",
                'etapes_terminees' => 5,
                'avancement' => 100,
                'date_debut' => '2025-06-01',
                'date_fin' => '2026-07-14',
                'budget' => 950_000_000,
                'lieu' => 'Rive ouest du lac, entrée par la rue des Pêcheurs',
                'latitude' => -18.9135,
                'longitude' => 47.5172,
            ],
            [
                'titre' => 'Extension du réseau d\'eau potable',
                'categorie' => 'eau',
                'quartier' => 'sud',
                'resume' => 'L\'eau du robinet dans 1 200 foyers du quartier Sud qui dépendent encore des bornes-fontaines.',
                'description' => "Dans une partie du quartier Sud, les familles vont encore chercher l'eau aux bornes-fontaines.\n\nNous posons 14 km de nouvelles canalisations et un réservoir de 2 000 m³. Chaque foyer pourra ensuite demander un branchement à prix réduit auprès du service des eaux.\n\nDes coupures d'eau courtes peuvent avoir lieu : elles sont annoncées 48 h à l'avance dans les actualités.",
                'etat' => 'en_cours',
                'etapes' => "Étude du terrain\nConstruction du réservoir\nPose des canalisations principales\nBranchements des foyers\nContrôle de la qualité de l'eau",
                'etapes_terminees' => 1,
                'avancement' => 30,
                'date_debut' => '2026-05-04',
                'date_fin' => '2027-12-15',
                'budget' => 4_500_000_000,
                'lieu' => 'Quartier Sud, secteurs Ambohitra et Tsaralalana',
                'latitude' => -18.9258,
                'longitude' => 47.5233,
            ],
            [
                'titre' => 'Éclairage public solaire',
                'categorie' => 'energie',
                'quartier' => null,
                'resume' => '800 lampadaires solaires pour des rues éclairées la nuit, même pendant les coupures.',
                'description' => "Beaucoup de rues sont dans le noir le soir, surtout pendant les coupures d'électricité.\n\nLa ville étudie l'installation de 800 lampadaires équipés de panneaux solaires et de batteries. Ils s'allument seuls à la tombée de la nuit et ne coûtent rien en électricité.\n\nLes rues prioritaires seront choisies avec les habitants : vous pourrez proposer la vôtre lors des réunions de quartier.",
                'etat' => 'etude',
                'etapes' => "Recensement des rues sans éclairage\nRéunions de quartier\nChoix du matériel\nInstallation par quartier\nBilan après un an",
                'etapes_terminees' => 1,
                'avancement' => 10,
                'date_debut' => null,
                'date_fin' => '2028-06-30',
                'budget' => null,
                'lieu' => 'Tous les quartiers',
                'latitude' => null,
                'longitude' => null,
            ],
        ];
    }
}
