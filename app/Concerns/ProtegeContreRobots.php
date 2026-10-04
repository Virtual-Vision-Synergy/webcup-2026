<?php

namespace App\Concerns;

use App\Models\TentativeBloquee;
use App\Services\ProtectionFormulaires;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * F81 : protection anti-robots des formulaires Livewire (champ piège, délai minimal, limites compte + IP).
 *
 *   mount() : $this->initialiserAntiRobot('contact');
 *   save()  : $this->verifierAntiRobot('contact', parCompte: 5, parIp: 15);   // après authorize, avant validate
 *   vue     : <x-anti-robot-livewire />
 *
 * Rejet : ValidationException sur la clé `throttle` (affichée par `<flux:error name="throttle" />`) et journal.
 */
trait ProtegeContreRobots
{
    /** Champ piège : un humain ne le voit pas, un robot le remplit. */
    public string $site_web = '';

    /** Jeton chiffré (formulaire + heure d'affichage), non modifiable par le navigateur. */
    #[Locked]
    public string $jetonAntiRobot = '';

    protected function initialiserAntiRobot(string $formulaire): void
    {
        $this->jetonAntiRobot = app(ProtectionFormulaires::class)->jeton($formulaire);
    }

    protected function verifierAntiRobot(string $formulaire, int $parCompte, int $parIp, int $decaySecondes = 60): void
    {
        $protection = app(ProtectionFormulaires::class);

        $motif = $protection->verifier($formulaire, $this->site_web, $this->jetonAntiRobot);
        $attente = 60;

        if ($motif === null) {
            $attente = $protection->limiter($formulaire, $parCompte, $parIp, $decaySecondes);
            $motif = $attente === null ? null : TentativeBloquee::MOTIF_DEBIT;
        }

        if ($motif === null) {
            return;
        }

        $protection->journaliser($formulaire, $motif);

        throw ValidationException::withMessages(['throttle' => $protection->message($motif, $attente ?? 60)]);
    }
}
