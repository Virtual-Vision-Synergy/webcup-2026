<?php

namespace Database\Seeders;

use App\Models\Annonce;
use App\Models\Onboarding;
use App\Models\Quartier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Alerte de démo F29 « Montée des eaux — quartier sud » et deux habitants pour la montrer (relançable sans doublon).
 * En production, l'alerte est publiée par la migration add_alerte_montee_des_eaux_to_annonces_table.
 */
class AlerteMonteeDesEauxSeeder extends Seeder
{
    public const TITRE = 'Montée des eaux — quartier sud';

    public function run(): void
    {
        if (! app()->isProduction()) {
            // Mot de passe : password. Habitant du quartier ciblé, et habitant d'un autre quartier.
            $this->habitant('sud@example.com', 'Rivo Andrianjaka', 'sud');
            $this->habitant('nord@example.com', 'Noro Rasoanaivo', 'nord');
        }

        self::publierAlerte();
    }

    /**
     * Crée l'alerte si elle n'existe pas déjà (même titre) ; sinon ne fait rien.
     */
    public static function publierAlerte(): ?Annonce
    {
        $existante = Annonce::query()->where('titre', self::TITRE)->first();
        $quartierSud = Quartier::idPour('sud');
        $auteur = User::query()
            ->whereIn('role_id', [Role::idFor(Role::AGENT), Role::idFor(Role::ADMIN)])
            ->orderBy('id')
            ->first();

        if ($existante !== null || $quartierSud === null || $auteur === null) {
            return $existante;
        }

        $alerte = new Annonce([
            'titre' => self::TITRE,
            'contenu' => 'Une montée inhabituelle du niveau de l’eau est observée dans le quartier sud. La situation est surveillée en continu par le Centre de surveillance environnementale ; de nouvelles informations seront publiées ici.',
            'consignes' => implode("\n", [
                'Éloignez-vous des berges, des canaux et des zones basses.',
                'Ne stationnez pas et ne circulez pas dans les parkings souterrains.',
                'Mettez à l’abri en hauteur vos documents importants et objets de valeur.',
                'Suivez les mises à jour de cette alerte sur la plateforme.',
                'En cas de danger immédiat, appelez les secours (numéro d’urgence affiché en mairie).',
            ]),
            'niveau' => 'alerte',
            'quartier_id' => $quartierSud,
            'debut' => now(),
            'fin' => now()->addDays(30),
        ]);
        $alerte->user()->associate($auteur);
        $alerte->save();

        return $alerte;
    }

    private function habitant(string $email, string $nom, string $quartier): void
    {
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => $nom, 'password' => 'password']);
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'quartier_id' => Quartier::idPour($quartier),
        ])->save();

        if (! $user->onboarding()->exists()) {
            Onboarding::factory()->termine()->for($user)->create();
        }
    }
}
