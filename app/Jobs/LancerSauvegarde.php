<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Sauvegardes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F87 : sauvegarde demandée depuis l'admin, puis vérification immédiate du fichier produit.
 * Unique : un seul export à la fois, même si le bouton est cliqué plusieurs fois.
 */
class LancerSauvegarde implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 320;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public ?int $userId = null) {}

    public function handle(Sauvegardes $sauvegardes): void
    {
        $auteur = $this->userId === null ? null : User::find($this->userId);

        try {
            $sauvegardes->lancer();
        } catch (Throwable $e) {
            // Message seul (jamais la sortie du script ni la configuration de la base).
            Log::error('F87 : sauvegarde manuelle en échec', ['message' => $e->getMessage()]);
            $sauvegardes->enregistrerEchecCreation($auteur);

            return;
        }

        $sauvegardes->verifierDerniere($auteur);
    }
}
