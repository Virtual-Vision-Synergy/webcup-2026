<?php

namespace Database\Seeders;

use App\Models\Annonce;
use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\Role;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Journal d'audit de démonstration (F47) : une trentaine d'opérations variées sur les 5 derniers jours,
 * par plusieurs auteurs, pour démontrer les filtres (type, auteur, élément, dates).
 * À lancer après les comptes de démo (appelé par DatabaseSeeder, hors production).
 */
class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $agent = User::where('email', 'agent@example.com')->first();
        $juryAgent = User::where('email', 'jury.agent@example.com')->first();
        $citoyen = User::where('email', 'user@example.com')->first();

        if (! $admin || ! $agent || ! $juryAgent || ! $citoyen) {
            return;
        }

        $citoyens = User::citizens()->where('email', 'like', '%@example.com')->whereNotIn('email', ['user@example.com'])->take(5)->get();
        $services = Service::query()->take(4)->get();
        $annonces = Annonce::query()->take(3)->get();
        $demarches = Demarche::query()->take(5)->get();
        $signalements = Signalement::query()->take(4)->get();

        $debut = now()->subDays(5)->setTime(8, 0);
        $minutes = 0;
        $quand = function () use ($debut, &$minutes): CarbonInterface {
            $minutes += 150 + random_int(0, 60);

            return $debut->copy()->addMinutes($minutes);
        };

        $entrees = [];

        foreach ($services->take(2) as $service) {
            $entrees[] = [$admin, 'created', $service, ['nom' => ['avant' => null, 'apres' => $service->nom], 'horaires' => ['avant' => null, 'apres' => $service->horaires]]];
        }
        foreach ($services as $service) {
            $entrees[] = [$juryAgent, 'updated', $service, ['horaires' => ['avant' => 'Lun-Ven 8h-16h', 'apres' => $service->horaires], 'telephone' => ['avant' => '020 22 000 00', 'apres' => $service->telephone]]];
        }
        foreach ($annonces as $annonce) {
            $entrees[] = [$agent, 'created', $annonce, ['titre' => ['avant' => null, 'apres' => $annonce->titre], 'niveau' => ['avant' => null, 'apres' => $annonce->niveau]]];
            $entrees[] = [$agent, 'updated', $annonce, ['niveau' => ['avant' => 'information', 'apres' => $annonce->niveau]]];
        }
        foreach ($demarches as $demarche) {
            $entrees[] = [$demarche->user ?? $citoyen, 'created', $demarche, ['titre' => ['avant' => null, 'apres' => $demarche->titre]]];
            $entrees[] = [$juryAgent, 'status_changed', $demarche, ['statut' => ['avant' => 'Déposée', 'apres' => 'En cours']]];
        }
        foreach ($signalements as $signalement) {
            $entrees[] = [$agent, 'status_changed', $signalement, ['statut' => ['avant' => 'Nouveau', 'apres' => 'En cours']]];
        }
        foreach ($citoyens->take(3) as $compte) {
            $entrees[] = [$agent, 'deactivated', $compte, ['deactivated_at' => ['avant' => null, 'apres' => now()->subDays(2)->format('Y-m-d H:i:s')]]];
        }
        foreach ($citoyens->take(2) as $compte) {
            $entrees[] = [$juryAgent, 'reactivated', $compte, ['deactivated_at' => ['avant' => now()->subDays(2)->format('Y-m-d H:i:s'), 'apres' => null]]];
        }
        $entrees[] = [$admin, 'role_changed', $juryAgent, ['role' => ['avant' => 'Citoyen', 'apres' => Role::where('code', Role::AGENT)->value('label')]]];
        $entrees[] = [$admin, 'role_changed', $agent, ['role' => ['avant' => 'Citoyen', 'apres' => Role::where('code', Role::AGENT)->value('label')]]];
        $entrees[] = [$citoyen, 'updated', $citoyen, ['password' => ['avant' => AuditLog::MASQUE, 'apres' => AuditLog::MASQUE]]];

        foreach ($entrees as [$auteur, $action, $element, $changes]) {
            AuditLog::factory()->par($auteur)->action($action)->create([
                'subject_type' => class_basename($element),
                'subject_id' => $element->getKey(),
                'subject_label' => AuditLogger::labelFor($element),
                'changes' => $changes,
                'ip' => '102.16.'.random_int(1, 254).'.'.random_int(1, 254),
                'created_at' => $quand(),
            ]);
        }

        // Suppressions : l'élément n'existe plus (la fiche affiche « élément supprimé »).
        foreach ([['Service', 'Service : Bureau des objets trouvés', 'nom', 'Bureau des objets trouvés'], ['Annonce', 'Message général : Test de diffusion', 'titre', 'Test de diffusion']] as [$type, $label, $champ, $valeur]) {
            AuditLog::factory()->par($admin)->action('deleted')->create([
                'subject_type' => $type,
                'subject_id' => 9000 + random_int(1, 99),
                'subject_label' => $label,
                'changes' => [$champ => ['avant' => $valeur, 'apres' => null]],
                'created_at' => $quand(),
            ]);
        }
    }
}
