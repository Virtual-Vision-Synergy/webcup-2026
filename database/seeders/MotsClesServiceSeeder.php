<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * D10 : synonymes de départ, en langage courant, pour les services de l'annuaire (retrouvés par leur nom).
 * Idempotent : un service absent est ignoré, un mot déjà présent n'est pas dupliqué.
 * Appelé par DatabaseSeeder (démo locale) et par la migration 2026_10_04_090001 (production, sans db:seed).
 * L'admin les complète ou les corrige ensuite dans Filament (Mots-clés d'orientation).
 */
class MotsClesServiceSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    public const MOTS_CLES = [
        'État civil' => ['papiers', 'acte de naissance', 'naissance', 'mariage', 'se marier', 'décès', 'livret de famille', 'extrait', 'copie intégrale', 'certificat de résidence', 'légalisation', 'carte d’identité', 'passeport'],
        'Urbanisme' => ['permis de construire', 'construire', 'construction', 'maison', 'terrain', 'travaux', 'cadastre', 'agrandissement', 'clôture'],
        'Services techniques et voirie' => ['route', 'trou', 'nid de poule', 'trottoir', 'lampadaire', 'éclairage', 'lumière', 'panne', 'égout', 'caniveau', 'arbre', 'encombrants', 'voirie'],
        'Action sociale (CCAS)' => ['aide', 'aide sociale', 'pauvreté', 'argent', 'repas', 'personne âgée', 'handicap', 'logement', 'sans abri', 'précarité', 'secours'],
        'Médiathèque Ravinala' => ['livre', 'bibliothèque', 'lecture', 'emprunter', 'bd', 'internet', 'ordinateur', 'film'],
        'Petite enfance et écoles' => ['école', 'inscription scolaire', 'crèche', 'bébé', 'enfant', 'cantine', 'garderie', 'bus scolaire', 'maternelle'],
        'Sports et associations' => ['sport', 'stade', 'gymnase', 'football', 'basket', 'club', 'association', 'subvention'],
        'Police municipale' => ['police', 'vol', 'bruit', 'voisin', 'sécurité', 'stationnement', 'voiture mal garée', 'objet perdu', 'objets trouvés', 'agression'],
        'Accueil de la Mairie' => ['renseignement', 'information', 'je ne sais pas', 'formulaire', 'aide démarche', 'mairie', 'accueil'],
        'Élections et recensement' => ['voter', 'vote', 'carte électorale', 'liste électorale', 'élection', 'recensement', 'déménagement'],
        'Environnement et propreté' => ['poubelle', 'déchets', 'ordures', 'ramassage', 'collecte', 'tri', 'recyclage', 'compost', 'sale', 'saleté', 'dépôt sauvage', 'propreté'],
        'Culture et festivités' => ['fête', 'festival', 'concert', 'spectacle', 'théâtre', 'salle des fêtes', 'musique', 'artiste'],
        'Marchés et commerce' => ['marché', 'commerce', 'commerçant', 'boutique', 'vendre', 'vendeur', 'place au marché', 'licence', 'tsena'],
        'Santé publique' => ['santé', 'médecin', 'docteur', 'malade', 'maladie', 'vaccin', 'vaccination', 'soins', 'consultation', 'paludisme', 'hôpital', 'dispensaire'],
        'Cimetière et affaires funéraires' => ['cimetière', 'enterrement', 'obsèques', 'inhumation', 'tombe', 'concession', 'funérailles', 'défunt'],
        'Jeunesse' => ['jeune', 'emploi', 'travail', 'stage', 'bourse', 'étudiant', 'orientation', 'maison des jeunes'],
    ];

    public function run(): void
    {
        $maintenant = now();
        $services = DB::table('services')->whereIn('nom', array_keys(self::MOTS_CLES))->pluck('id', 'nom');
        $lignes = [];

        foreach (self::MOTS_CLES as $nomService => $mots) {
            if (! isset($services[$nomService])) {
                continue;
            }

            foreach ($mots as $mot) {
                $lignes[] = ['service_id' => $services[$nomService], 'mot' => $mot, 'created_at' => $maintenant, 'updated_at' => $maintenant];
            }
        }

        if ($lignes !== []) {
            DB::table('mots_cles_service')->insertOrIgnore($lignes);
        }
    }
}
