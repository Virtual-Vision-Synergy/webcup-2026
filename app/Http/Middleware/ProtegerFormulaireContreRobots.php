<?php

namespace App\Http\Middleware;

use App\Services\ProtectionFormulaires;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F81 : formulaires Blade publics (connexion, inscription). Rejette l'envoi si le champ piège est rempli,
 * si le jeton d'horodatage est absent / falsifié / expiré, ou si l'envoi suit l'affichage de trop près.
 * Le rejet est journalisé ; la personne revient sur le formulaire avec un message, sans perdre sa saisie.
 *
 * Utilisation : ->middleware(ProtegerFormulaireContreRobots::class.':inscription')
 */
class ProtegerFormulaireContreRobots
{
    public function __construct(private ProtectionFormulaires $protection) {}

    public function handle(Request $request, Closure $next, string $formulaire): Response
    {
        $motif = $this->protection->verifier(
            $formulaire,
            $request->input(ProtectionFormulaires::CHAMP_PIEGE),
            $request->input(ProtectionFormulaires::CHAMP_JETON),
        );

        if ($motif === null) {
            return $next($request);
        }

        $this->protection->journaliser($formulaire, $motif);

        return back()
            ->withInput($request->except(['password', 'password_confirmation', ProtectionFormulaires::CHAMP_PIEGE, ProtectionFormulaires::CHAMP_JETON]))
            ->withErrors(['formulaire' => $this->protection->message($motif)]);
    }
}
