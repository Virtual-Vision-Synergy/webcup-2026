<?php

namespace App\Services;

use App\Models\FormSubmission;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * F82 : un même envoi de formulaire n'est traité qu'une fois.
 *
 * Doublon si le jeton du formulaire a déjà servi, ou si le même habitant a envoyé le même contenu (même empreinte)
 * sur le même formulaire depuis moins de `security.doublons.fenetre_minutes`. La création et l'enregistrement
 * de l'envoi sont faits dans une transaction : le jeton n'est consommé que si la création réussit.
 */
class EnvoisUniques
{
    /**
     * Empreinte sha256 des seuls champs métier (jamais le jeton, le CSRF, les champs anti-robots ni les fichiers),
     * normalisés : espaces en début et fin retirés, espaces multiples réduits.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function empreinte(array $donnees): string
    {
        ksort($donnees);

        $normalisees = array_map(
            fn (mixed $valeur): mixed => is_string($valeur) ? (string) preg_replace('/\s+/u', ' ', trim($valeur)) : $valeur,
            $donnees,
        );

        return hash('sha256', json_encode($normalisees, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * L'envoi déjà enregistré dont celui-ci est le doublon, ou null.
     */
    public function doublon(string $formulaire, string $jeton, string $empreinte): ?FormSubmission
    {
        $parJeton = FormSubmission::query()->where('token', $jeton)->first();

        if ($parJeton !== null) {
            return $parJeton;
        }

        $userId = Auth::id();

        if ($userId === null) {
            return null;
        }

        return FormSubmission::query()
            ->where('user_id', $userId)
            ->where('form_key', $formulaire)
            ->where('content_hash', $empreinte)
            ->where('created_at', '>=', now()->subMinutes((int) config('security.doublons.fenetre_minutes', 5)))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    /**
     * Crée l'enregistrement et note l'envoi, atomiquement. Retourne le FormSubmission existant si le jeton
     * a été utilisé entre-temps par une requête concurrente (rien n'est alors créé).
     *
     * @param  Closure(): Model  $creer
     */
    public function enregistrer(string $formulaire, string $jeton, string $empreinte, Closure $creer): Model|FormSubmission
    {
        try {
            return DB::transaction(function () use ($formulaire, $jeton, $empreinte, $creer): Model {
                $enregistrement = $creer();

                $envoi = new FormSubmission;
                $userId = Auth::id();
                $envoi->user_id = $userId === null ? null : (int) $userId;
                $envoi->form_key = $formulaire;
                $envoi->token = $jeton;
                $envoi->content_hash = $empreinte;
                $envoi->submittable()->associate($enregistrement);
                $envoi->save();

                return $enregistrement;
            });
        } catch (UniqueConstraintViolationException $e) {
            return FormSubmission::query()->where('token', $jeton)->first() ?? throw $e;
        }
    }
}
