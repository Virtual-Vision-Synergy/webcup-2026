<?php

namespace Database\Seeders;

use App\Models\Annonce;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ImportantAnnouncementPublished;
use Illuminate\Database\Seeder;

/**
 * Démo F30 (local uniquement) : notifications lues et non lues pour user@example.com (mot de passe : password),
 * et une annonce « Danger » programmée dans 5 minutes pour montrer l'envoi au bon moment
 * (php artisan schedule:work, ou php artisan annonces:notify une fois l'heure passée).
 */
class NotificationsAnnoncesSeeder extends Seeder
{
    public function run(): void
    {
        $citoyen = User::query()->where('email', 'user@example.com')->first();
        $auteur = User::query()->whereIn('role_id', [Role::idFor(Role::AGENT), Role::idFor(Role::ADMIN)])->orderBy('id')->first();

        if ($citoyen === null || $auteur === null) {
            return;
        }

        $demo = [
            // [titre, contenu, consignes, niveau, état, il y a (minutes), lue]
            ['Coupure d’électricité générale ce soir', 'Une intervention sur le poste source coupera l’électricité dans toute la ville de 20 h à 23 h.', "Chargez vos appareils avant 20 h.\nPrévoyez une lampe torche.", 'danger', 'active', 12, false],
            ['Fortes chaleurs : restez hydratés', 'Des températures supérieures à 36 °C sont attendues cet après-midi.', "Buvez de l’eau régulièrement.\nÉvitez les efforts entre 12 h et 16 h.", 'alerte', 'active', 95, false],
            ['Vents violents cette nuit', 'Des rafales jusqu’à 100 km/h ont touché la ville cette nuit.', 'Rentrez le mobilier extérieur.', 'alerte', 'expiree', 60 * 26, true],
            ['Route du nord inondée', 'La route du nord était fermée à la circulation à cause d’une inondation.', 'Empruntez la rocade est.', 'danger', 'expiree', 60 * 50, true],
        ];

        foreach ($demo as [$titre, $contenu, $consignes, $niveau, $etat, $ilYA, $lue]) {
            $annonce = Annonce::factory()->{$etat}()->for($auteur)->create(compact('titre', 'contenu', 'consignes', 'niveau'));
            $annonce->forceFill(['notified_at' => now()->subMinutes($ilYA)])->save();

            $citoyen->notifyNow(new ImportantAnnouncementPublished($annonce), ['database']);
            $citoyen->notifications()->latest()->first()?->forceFill([
                'created_at' => now()->subMinutes($ilYA),
                'read_at' => $lue ? now()->subMinutes($ilYA - 5) : null,
            ])->save();
        }

        Annonce::factory()->for($auteur)->create([
            'titre' => 'Exercice d’évacuation dans toute la ville',
            'contenu' => 'Un exercice d’évacuation grandeur nature a lieu maintenant : les sirènes vont retentir.',
            'consignes' => "Gagnez le point de regroupement le plus proche.\nN’utilisez pas les ascenseurs.",
            'niveau' => 'danger',
            'debut' => now()->addMinutes(5),
            'fin' => now()->addDay(),
        ]);
    }
}
