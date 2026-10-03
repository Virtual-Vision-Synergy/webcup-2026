<?php

namespace App\Events;

use App\Models\Signalement;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Une nouvelle étape apparaît dans le suivi d'un signalement (D11) : dépôt, puis chaque changement d'état.
 * Déclenché par le modèle quel que soit l'écran (agent, Filament, tinker). Aucun écouteur pour l'instant :
 * il servira à prévenir l'habitant quand sa demande change d'état (F49).
 */
class SignalementEtapeAjoutee
{
    use Dispatchable;

    public function __construct(
        public Signalement $signalement,
        public string $statut,
    ) {}
}
