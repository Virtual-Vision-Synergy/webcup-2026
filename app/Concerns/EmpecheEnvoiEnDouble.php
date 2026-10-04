<?php

namespace App\Concerns;

use App\Models\FormSubmission;
use App\Services\EnvoisUniques;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * F82 : empêche l'envoi en double d'un formulaire Livewire (double clic, retour arrière, rafraîchissement).
 *
 *   mount() : $this->initialiserJetonEnvoi();
 *   save()  : authorize → validate → F81 → $record = $this->envoyerUneSeuleFois('signalement', [...], fn () => ..., fn ($r) => route(...));
 *             if ($record === null) { return; }   // doublon : rien n'est créé, le message s'affiche
 *   vue     : <x-envoi-deja-fait :le="$envoiDejaFaitLe" :url="$envoiDejaFaitUrl" />  et  <x-submit-button>
 */
trait EmpecheEnvoiEnDouble
{
    /** Jeton unique de cet affichage du formulaire, non modifiable par le navigateur. */
    #[Locked]
    public string $jetonEnvoi = '';

    /** Doublon détecté : date lisible de l'envoi d'origine. */
    #[Locked]
    public ?string $envoiDejaFaitLe = null;

    /** Doublon détecté : page de détail de l'enregistrement d'origine (toujours un enregistrement de l'habitant). */
    #[Locked]
    public ?string $envoiDejaFaitUrl = null;

    protected function initialiserJetonEnvoi(): void
    {
        $this->jetonEnvoi = (string) Str::uuid();
    }

    /**
     * Crée l'enregistrement si ce n'est pas un doublon. Retourne null (et prépare le message) sinon.
     *
     * @template TModel of Model
     *
     * @param  array<string, mixed>  $donneesEmpreinte  champs métier validés seulement
     * @param  Closure(): TModel  $creer
     * @param  Closure(TModel): string  $urlDetail
     * @return TModel|null
     */
    protected function envoyerUneSeuleFois(string $formulaire, array $donneesEmpreinte, Closure $creer, Closure $urlDetail): ?Model
    {
        $envois = app(EnvoisUniques::class);
        $empreinte = $envois->empreinte($donneesEmpreinte);

        if ($this->jetonEnvoi === '') {
            $this->initialiserJetonEnvoi();
        }

        $resultat = $envois->doublon($formulaire, $this->jetonEnvoi, $empreinte)
            ?? $envois->enregistrer($formulaire, $this->jetonEnvoi, $empreinte, $creer);

        if ($resultat instanceof FormSubmission) {
            $this->signalerEnvoiDejaFait($resultat, $urlDetail);

            return null;
        }

        $this->envoiDejaFaitLe = null;
        $this->envoiDejaFaitUrl = null;
        $this->initialiserJetonEnvoi();

        return $resultat;
    }

    /**
     * @param  Closure(Model): string  $urlDetail
     */
    private function signalerEnvoiDejaFait(FormSubmission $envoi, Closure $urlDetail): void
    {
        $this->envoiDejaFaitLe = $envoi->envoyeLe();

        // Le lien ne mène qu'à un enregistrement de l'habitant connecté (jamais celui d'un autre).
        $enregistrement = $envoi->submittable;
        $this->envoiDejaFaitUrl = $enregistrement !== null && $envoi->user_id !== null && $envoi->user_id === auth()->id()
            ? $urlDetail($enregistrement)
            : null;
    }
}
