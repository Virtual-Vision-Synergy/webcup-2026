<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Annuaire des services de la Mairie de Nova Terra. Idempotent : un service déjà présent (même nom) est ignoré.
 */
class ServiceSeeder extends Seeder
{
    /** Catégorie de chaque service de l'annuaire (voir Service::CATEGORIE_OPTIONS). */
    private const CATEGORIES = [
        'État civil' => 'administratif',
        'Accueil de la Mairie' => 'administratif',
        'Élections et recensement' => 'administratif',
        'Cimetière et affaires funéraires' => 'administratif',
        'Urbanisme' => 'urbanisme',
        'Services techniques et voirie' => 'urbanisme',
        'Environnement et propreté' => 'urbanisme',
        'Santé publique' => 'sante',
        'Action sociale (CCAS)' => 'social',
        'Petite enfance et écoles' => 'education',
        'Jeunesse' => 'education',
        'Médiathèque Ravinala' => 'culture',
        'Sports et associations' => 'culture',
        'Culture et festivités' => 'culture',
        'Police municipale' => 'securite',
        'Marchés et commerce' => 'economie',
    ];

    public function run(): void
    {
        $auteur = User::query()->where('role', 'admin')->first() ?? User::query()->first();

        if ($auteur === null) {
            return;
        }

        foreach ($this->services() as $data) {
            $data['categorie'] = self::CATEGORIES[$data['nom']] ?? null;

            $existant = Service::query()->where('nom', $data['nom'])->first();

            if ($existant !== null) {
                if ($existant->categorie === null && $data['categorie'] !== null) {
                    $existant->update(['categorie' => $data['categorie']]);
                }

                continue;
            }

            $service = new Service($data);
            // DatabaseSeeder désactive les événements de modèle : le slug est donc posé ici.
            $service->slug = Service::uniqueSlug($data['nom']);
            $service->user()->associate($auteur);
            $service->save();
        }
    }

    /**
     * @return array<int, array{nom: string, description: string, horaires: string|null, telephone: string|null, email: string|null, adresse: string|null}>
     */
    private function services(): array
    {
        return [
            [
                'nom' => 'État civil',
                'description' => "Actes de naissance, de mariage et de décès, livrets de famille, légalisation de signature et certificats de résidence.\nPrise de rendez-vous conseillée pour les mariages et les reconnaissances.",
                'horaires' => "Lundi au vendredi : 8 h 00 – 16 h 00\nSamedi : 8 h 00 – 11 h 30 (permanence naissances et décès)",
                'telephone' => '+261 20 22 401 10',
                'email' => 'etat-civil@mairie-novaterra.mg',
                'adresse' => "Hôtel de ville, place de l'Indépendance, Nova Terra",
            ],
            [
                'nom' => 'Urbanisme',
                'description' => "Permis de construire, déclarations de travaux, certificats d'urbanisme et consultation du plan local d'urbanisme.\nUn architecte-conseil reçoit gratuitement les habitants sur rendez-vous.",
                'horaires' => "Lundi, mercredi et jeudi : 8 h 30 – 12 h 00 et 14 h 00 – 16 h 30\nMardi et vendredi : sur rendez-vous",
                'telephone' => '+261 20 22 401 25',
                'email' => 'urbanisme@mairie-novaterra.mg',
                'adresse' => 'Centre administratif, 12 rue des Bâtisseurs, Nova Terra',
            ],
            [
                'nom' => 'Services techniques et voirie',
                'description' => "Entretien des routes et trottoirs, éclairage public, signalisation, espaces verts et collecte des encombrants.\nSignalez un nid-de-poule ou un lampadaire en panne : intervention sous 72 h.",
                'horaires' => 'Lundi au vendredi : 7 h 30 – 15 h 30',
                'telephone' => '+261 20 22 401 40',
                'email' => 'services-techniques@mairie-novaterra.mg',
                'adresse' => 'Centre technique municipal, zone des Ateliers, Nova Terra',
            ],
            [
                'nom' => 'Action sociale (CCAS)',
                'description' => "Le Centre communal d'action sociale accompagne les familles, les personnes âgées et les personnes en situation de handicap : aides d'urgence, portage de repas, domiciliation et orientation vers les associations partenaires.",
                'horaires' => "Lundi au vendredi : 8 h 00 – 12 h 00 et 13 h 30 – 16 h 30\nAccueil sans rendez-vous le mercredi matin",
                'telephone' => '+261 20 22 401 55',
                'email' => 'ccas@mairie-novaterra.mg',
                'adresse' => '4 allée de la Solidarité, quartier Ambohitra, Nova Terra',
            ],
            [
                'nom' => 'Médiathèque Ravinala',
                'description' => 'Plus de 25 000 livres, bandes dessinées, journaux et films. Prêt gratuit pour les habitants, espace numérique avec accès Internet, heure du conte pour les enfants le samedi.',
                'horaires' => "Mardi au vendredi : 9 h 00 – 18 h 00\nSamedi : 9 h 00 – 17 h 00\nFermée le dimanche et le lundi",
                'telephone' => '+261 20 22 402 10',
                'email' => 'mediatheque@mairie-novaterra.mg',
                'adresse' => '8 boulevard des Lettres, Nova Terra',
            ],
            [
                'nom' => 'Petite enfance et écoles',
                'description' => 'Inscriptions en crèche municipale et dans les écoles primaires publiques, cantine scolaire, garderie périscolaire et transport scolaire.',
                'horaires' => "Lundi au vendredi : 8 h 00 – 15 h 30\nPériode d'inscription scolaire : du 1er au 30 juin",
                'telephone' => '+261 20 22 402 30',
                'email' => 'education@mairie-novaterra.mg',
                'adresse' => 'Maison de l\'enfance, 21 rue des Écoliers, Nova Terra',
            ],
            [
                'nom' => 'Sports et associations',
                'description' => 'Réservation des équipements sportifs (stade municipal, gymnase, terrains de basket), subventions et accompagnement des associations, annuaire des clubs et forum des associations en septembre.',
                'horaires' => 'Lundi au vendredi : 9 h 00 – 12 h 00 et 14 h 00 – 17 h 00',
                'telephone' => '+261 20 22 402 45',
                'email' => 'sports-associations@mairie-novaterra.mg',
                'adresse' => 'Complexe sportif municipal, avenue du Stade, Nova Terra',
            ],
            [
                'nom' => 'Police municipale',
                'description' => "Tranquillité publique, sécurité aux abords des écoles, stationnement, objets trouvés et opération « Tranquillité vacances ». En cas d'urgence, composez le 117.",
                'horaires' => "Accueil : tous les jours de 7 h 00 à 19 h 00\nPatrouilles 24 h/24",
                'telephone' => '+261 20 22 401 17',
                'email' => 'police-municipale@mairie-novaterra.mg',
                'adresse' => "Poste de police municipale, place de l'Indépendance, Nova Terra",
            ],
            [
                'nom' => 'Accueil de la Mairie',
                'description' => 'Premier point de contact des habitants : information sur toutes les démarches, orientation vers le bon service, retrait des formulaires administratifs et prise de rendez-vous.',
                'horaires' => "Lundi au vendredi : 7 h 30 – 17 h 00\nSamedi : 8 h 00 – 12 h 00",
                'telephone' => '+261 20 22 401 00',
                'email' => 'accueil@mairie-novaterra.mg',
                'adresse' => "Hôtel de ville, place de l'Indépendance, Nova Terra",
            ],
            [
                'nom' => 'Élections et recensement',
                'description' => "Inscription sur les listes électorales, changement d'adresse, attestations d'inscription, organisation des bureaux de vote et recensement citoyen des jeunes de 16 ans.",
                'horaires' => 'Lundi au jeudi : 8 h 30 – 12 h 00 et 14 h 00 – 16 h 00',
                'telephone' => '+261 20 22 401 30',
                'email' => 'elections@mairie-novaterra.mg',
                'adresse' => "Hôtel de ville, 1er étage, place de l'Indépendance, Nova Terra",
            ],
            [
                'nom' => 'Environnement et propreté',
                'description' => 'Collecte des déchets ménagers, tri sélectif, points de compost de quartier, nettoyage des marchés et lutte contre les dépôts sauvages. Calendrier de collecte disponible sur demande.',
                'horaires' => 'Lundi au samedi : 6 h 00 – 14 h 00',
                'telephone' => '+261 20 22 401 45',
                'email' => 'proprete@mairie-novaterra.mg',
                'adresse' => 'Dépôt municipal, route de la Digue, Nova Terra',
            ],
            [
                'nom' => 'Culture et festivités',
                'description' => 'Programmation de la salle des fêtes et du théâtre de verdure, Fête de la ville en juin, soutien aux artistes locaux et prêt de matériel (podium, chaises, sonorisation) aux associations.',
                'horaires' => 'Mardi au vendredi : 9 h 00 – 17 h 00',
                'telephone' => '+261 20 22 402 60',
                'email' => 'culture@mairie-novaterra.mg',
                'adresse' => 'Maison de la culture, 3 place des Arts, Nova Terra',
            ],
            [
                'nom' => 'Marchés et commerce',
                'description' => "Attribution des places sur les marchés municipaux (Tsena Be et marché couvert), autorisations d'occupation du domaine public, licences de débit de boissons et relations avec les commerçants.",
                'horaires' => 'Lundi au vendredi : 7 h 00 – 13 h 00',
                'telephone' => '+261 20 22 402 75',
                'email' => 'marches@mairie-novaterra.mg',
                'adresse' => 'Bureau du marché couvert, rue du Commerce, Nova Terra',
            ],
            [
                'nom' => 'Santé publique',
                'description' => "Centre de santé municipal : consultations de médecine générale, vaccinations gratuites, suivi des nourrissons et campagnes de prévention (paludisme, hygiène de l'eau).",
                'horaires' => "Lundi au vendredi : 7 h 30 – 16 h 00\nVaccinations : mercredi matin",
                'telephone' => '+261 20 22 403 00',
                'email' => 'sante@mairie-novaterra.mg',
                'adresse' => '15 rue Pasteur, quartier Ambohitra, Nova Terra',
            ],
            [
                'nom' => 'Cimetière et affaires funéraires',
                'description' => "Concessions funéraires, autorisations d'inhumation et d'exhumation, entretien du cimetière municipal. Les familles sont reçues sur rendez-vous.",
                'horaires' => null,
                'telephone' => '+261 20 22 401 90',
                'email' => null,
                'adresse' => 'Cimetière municipal, route d\'Ambatomena, Nova Terra',
            ],
            [
                'nom' => 'Jeunesse',
                'description' => 'Point information jeunesse : orientation, aide aux premiers emplois et stages, bourses municipales, activités de la maison des jeunes pendant les vacances scolaires.',
                'horaires' => "Lundi au vendredi : 10 h 00 – 18 h 00\nSamedi : 9 h 00 – 13 h 00",
                'telephone' => null,
                'email' => 'jeunesse@mairie-novaterra.mg',
                'adresse' => 'Maison des jeunes, 7 avenue de la Jeunesse, Nova Terra',
            ],
        ];
    }
}
