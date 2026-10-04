<?php

namespace Database\Seeders;

use App\Models\Demarche;
use App\Models\Service;
use App\Models\ServiceReview;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Avis des habitants sur les services (F76). Idempotent (clé : habitant + service, contrainte unique).
 *
 * Dix avis sur trois services, notes variées, deux réponses d'agents et un avis masqué (État civil :
 * modérable par agent.etat-civil@example.com). user@example.com reçoit une démarche « Urbanisme » traitée,
 * sans avis : c'est le point de départ de la démonstration (« Donnez votre avis »).
 */
class ServiceReviewSeeder extends Seeder
{
    /** @var array<string, list<array{email: string, rating: int, comment: string, jours: int, verifie?: bool, reponse?: string, masque?: string}>> */
    private const AVIS = [
        'État civil' => [
            ['email' => 'hanitra.rakoto@example.com', 'rating' => 5, 'jours' => 9, 'verifie' => true, 'comment' => 'Acte de naissance obtenu en dix minutes, agent très aimable. Rien à redire.'],
            ['email' => 'tojo.andria@example.com', 'rating' => 2, 'jours' => 6, 'comment' => 'Plus d’une heure d’attente un lundi matin, un seul guichet ouvert sur trois.', 'reponse' => 'Merci pour votre retour. Depuis cette semaine, un deuxième guichet est ouvert le lundi matin, jour le plus chargé.'],
            ['email' => 'awa.diallo@example.com', 'rating' => 4, 'jours' => 4, 'verifie' => true, 'comment' => 'Transcription de mariage bien expliquée. Seul regret : la liste des pièces n’était pas claire en ligne.'],
            ['email' => 'lucas.moreau@example.com', 'rating' => 1, 'jours' => 2, 'comment' => 'L’agent du guichet 2 s’appelle Rabe et habite rue des Lilas, il est nul, appelez-le au 034 00 000 00.', 'masque' => 'donnees_personnelles'],
        ],
        'Action sociale (CCAS)' => [
            ['email' => 'hanitra.rakoto@example.com', 'rating' => 5, 'jours' => 12, 'verifie' => true, 'comment' => 'Écoute bienveillante, l’assistante sociale m’a aidée à monter mon dossier d’aide au loyer.', 'reponse' => 'Merci beaucoup pour ce message, nous le transmettons à l’équipe.'],
            ['email' => 'awa.diallo@example.com', 'rating' => 3, 'jours' => 7, 'comment' => 'Accueil correct, mais il faut revenir plusieurs fois pour un même dossier.'],
            ['email' => 'lucas.moreau@example.com', 'rating' => 4, 'jours' => 3, 'comment' => 'Rendez-vous pris en ligne et respecté à l’heure, c’est appréciable.'],
        ],
        'Médiathèque Ravinala' => [
            ['email' => 'tojo.andria@example.com', 'rating' => 5, 'jours' => 10, 'comment' => 'Très bel espace jeunesse, les ateliers du mercredi plaisent beaucoup à mes enfants.'],
            ['email' => 'user@example.com', 'rating' => 4, 'jours' => 5, 'comment' => 'Bon choix de livres en malgache et en français, la connexion Wi-Fi est parfois lente.'],
            ['email' => 'awa.diallo@example.com', 'rating' => 3, 'jours' => 1, 'comment' => 'Horaires du samedi trop courts pour ceux qui travaillent en semaine.'],
        ],
    ];

    public function run(): void
    {
        $services = Service::query()->whereIn('nom', array_keys(self::AVIS))->get()->keyBy('nom');
        $habitants = User::query()->whereIn('email', collect(self::AVIS)->flatten(1)->pluck('email')->unique())->get()->keyBy('email');
        $agent = User::query()->where('email', 'agent.etat-civil@example.com')->first()
            ?? User::query()->where('email', 'agent@example.com')->first();

        foreach (self::AVIS as $nomService => $avisDuService) {
            $service = $services[$nomService] ?? null;

            if ($service === null) {
                continue;
            }

            foreach ($avisDuService as $exemple) {
                $habitant = $habitants[$exemple['email']] ?? null;

                if ($habitant === null || ServiceReview::query()->where('user_id', $habitant->id)->where('service_id', $service->id)->exists()) {
                    continue;
                }

                $date = now()->subDays($exemple['jours']);

                $avis = new ServiceReview(['rating' => $exemple['rating'], 'comment' => $exemple['comment']]);
                $avis->user_id = $habitant->id;
                $avis->service_id = $service->id;
                $avis->verified_usage = $exemple['verifie'] ?? false;
                $avis->created_at = $date;
                $avis->updated_at = $date;

                if (isset($exemple['reponse']) && $agent !== null) {
                    $avis->response = $exemple['reponse'];
                    $avis->responded_at = $date->copy()->addDay();
                    $avis->responded_by = $agent->id;
                }

                if (isset($exemple['masque'])) {
                    $avis->hidden_at = $date->copy()->addHours(3);
                    $avis->hidden_by = $agent?->id;
                    $avis->hidden_reason = $exemple['masque'];
                }

                $avis->save();
            }
        }

        $this->demarcheTraiteePourLaDemo();
    }

    /**
     * Démarche traitée de user@example.com sur « Urbanisme » : invitation « Donnez votre avis » et badge vérifié.
     */
    private function demarcheTraiteePourLaDemo(): void
    {
        $demo = User::query()->where('email', 'user@example.com')->first();
        $urbanisme = Service::query()->where('nom', 'Urbanisme')->first();
        $titre = 'Déclaration préalable pour une clôture';

        if ($demo === null || $urbanisme === null || Demarche::query()->where('user_id', $demo->id)->where('titre', $titre)->exists()) {
            return;
        }

        $demarche = new Demarche([
            'titre' => $titre,
            'description' => 'Je souhaite remplacer la haie de mon jardin par une clôture grillagée de 1,50 m.',
            'service_id' => $urbanisme->id,
        ]);
        $demarche->user()->associate($demo);
        $demarche->statut = 'traitee';
        $demarche->save();
    }
}
