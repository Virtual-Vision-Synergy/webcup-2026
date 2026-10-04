<?php

namespace Database\Seeders;

use App\Models\Demarche;
use App\Models\Role;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use App\Services\RapportActivite;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * F103 : données de démo pour que le « Rapport d'activité » raconte quelque chose.
 *  - une date de réponse crédible pour les démarches de démo clôturées (sinon le délai moyen vaut zéro),
 *    plus longue récemment pour l'État civil (point d'attention « le délai a augmenté ») ;
 *  - 60 jours de signalements datés, plus nombreux dans le quartier Sud.
 * Idempotent : ne retouche que les démarches dont la réponse a la même date que le dépôt,
 * et ne crée les signalements datés qu'une fois.
 */
class RapportActiviteSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Service dont le délai s'allonge sur la période récente. */
    private const SERVICE_RALENTI = 'État civil';

    public function run(): void
    {
        $this->daterLesReponses();
        $this->creerLesSignalements();
    }

    private function daterLesReponses(): void
    {
        $ralenti = Service::query()->where('nom', self::SERVICE_RALENTI)->value('id');

        Demarche::query()->toBase()
            ->whereIn('statut', RapportActivite::STATUTS_TERMINES)
            ->whereColumn('updated_at', 'created_at')
            ->where('created_at', '<', now()->subDays(2))
            ->orderBy('id')
            ->get(['id', 'service_id', 'created_at'])
            ->each(function (object $demarche) use ($ralenti): void {
                $depot = Carbon::parse($demarche->created_at);
                $lent = $ralenti !== null && (int) $demarche->service_id === (int) $ralenti && $depot->gte(now()->subDays(45));
                $reponse = $depot->copy()->addHours($lent ? mt_rand(150, 240) : mt_rand(30, 110));

                Demarche::query()->toBase()->where('id', $demarche->id)->update(['updated_at' => $reponse->min(now())]);
            });
    }

    private function creerLesSignalements(): void
    {
        if (Signalement::query()->where('created_at', '<', now()->subDays(20))->exists()) {
            return;
        }

        $habitants = User::query()->where('role_id', Role::idFor(Role::CITOYEN))->with('quartierResidence')->get();

        if ($habitants->isEmpty()) {
            return;
        }

        $duSud = $habitants->filter(fn (User $habitant): bool => $habitant->quartierResidence?->slug === 'sud');

        for ($jour = 59; $jour >= 1; $jour--) {
            // Activité plus forte sur les 30 derniers jours, avec un signalement par jour impair dans le quartier Sud.
            $nombre = $jour % 2 === 0 ? 1 : ($jour < 30 ? 2 : 0);

            for ($i = 0; $i < $nombre; $i++) {
                $auteur = $jour < 30 && $i === 0 && $duSud->isNotEmpty() ? $duSud->random() : $habitants->random();
                $date = Carbon::today()->subDays($jour)->setTime(mt_rand(8, 19), mt_rand(0, 59));

                Signalement::factory()->for($auteur)->create([
                    'statut' => $jour > 10 ? fake()->randomElement(['resolu', 'resolu', 'en_cours', 'rejete']) : fake()->randomElement(['nouveau', 'en_cours']),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        }
    }
}
