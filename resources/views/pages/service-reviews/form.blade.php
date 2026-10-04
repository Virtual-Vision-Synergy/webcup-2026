<?php

use App\Concerns\EmpecheEnvoiEnDouble;
use App\Concerns\ThrottlesPerUser;
use App\Models\Service;
use App\Models\ServiceReview;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * F76 : donner ou modifier son avis sur un service. service_id vient de la route, jamais du formulaire ;
 * un seul avis par habitant et par service (pré-rempli s'il existe, mis à jour à l'envoi).
 */
new #[Title('Votre avis sur ce service')] class extends Component {
    use EmpecheEnvoiEnDouble, ThrottlesPerUser;

    #[Locked]
    public Service $service;

    #[Locked]
    public ?ServiceReview $review = null;

    public string $rating = '';

    public string $comment = '';

    /** Message de confirmation affiché après l'envoi (« Merci, votre avis a été enregistré le … »). */
    #[Locked]
    public ?string $confirmation = null;

    public function mount(Service $service): void
    {
        $this->authorize('view', $service);
        $this->service = $service;
        $this->initialiserJetonEnvoi();
        $this->review = ServiceReview::query()->whereBelongsTo(auth()->user())->whereBelongsTo($service)->first();

        if ($this->review !== null) {
            $this->rating = (string) $this->review->rating;
            $this->comment = $this->review->comment;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:'.ServiceReview::RATING_MIN.','.ServiceReview::RATING_MAX],
            'comment' => ['required', 'string', 'min:'.ServiceReview::COMMENT_MIN, 'max:'.ServiceReview::COMMENT_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'rating.required' => 'Choisissez une note de 1 à 5.',
            'rating.integer' => 'Choisissez une note de 1 à 5.',
            'rating.between' => 'Choisissez une note de 1 à 5.',
            'comment.required' => 'Écrivez un commentaire pour expliquer votre note.',
            'comment.min' => 'Votre commentaire doit faire au moins :min caractères.',
            'comment.max' => 'Votre commentaire ne doit pas dépasser :max caractères.',
        ];
    }

    public function save(): void
    {
        if ($this->review !== null) {
            $this->authorize('update', $this->review);
        } else {
            $this->authorize('create', [ServiceReview::class, $this->service]);
        }

        $this->throttlePerUser('avis-service', maxAttempts: 5, decaySeconds: 60);
        $donnees = $this->validate();

        try {
            // F82 : même note et même commentaire renvoyés (double clic, retour arrière) → rien n'est réécrit.
            $avis = $this->envoyerUneSeuleFois(
                'avis',
                ['service_id' => $this->service->id, 'rating' => (int) $donnees['rating'], 'comment' => $donnees['comment']],
                fn (): ServiceReview => ServiceReview::enregistrer(auth()->user(), $this->service, $donnees),
                fn (): string => route('services.reviews.mine'),
            );

            if ($avis === null) {
                $this->confirmation = null;

                return;
            }
        } catch (UniqueConstraintViolationException) {
            // Double envoi simultané : l'avis existe déjà, on le met à jour.
            $avis = ServiceReview::enregistrer(auth()->user(), $this->service, $donnees);
        }

        $creation = $avis->wasRecentlyCreated;
        $this->review = $avis;
        $this->confirmation = 'Merci, votre avis a été '.($creation ? 'enregistré' : 'mis à jour').' le '.ServiceReview::dateLongue($avis->updated_at).'.';

        Flux::toast(variant: 'success', text: $this->confirmation);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        :label="__('Avis des habitants')"
        :title="$review ? __('Modifier mon avis') : __('Votre avis sur ce service')"
        :subtitle="$service->nom"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Services') => route('services.index'), $service->nom => route('services.show', $service), __('Mon avis') => null]"
    />

    @if ($confirmation)
        <flux:callout variant="success" icon="check-circle" role="status">
            <flux:callout.heading>{{ $confirmation }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Votre avis est visible sur la fiche du service. Vous pouvez le modifier à tout moment.') }}
                <flux:link :href="route('services.reviews.mine')" wire:navigate>{{ __('Voir mes avis') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($review?->estMasque())
        <flux:callout variant="warning" icon="eye-slash" role="alert">
            <flux:callout.heading>{{ __('Votre avis a été masqué par la modération') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Motif : :motif. Il n’apparaît plus sur la fiche du service et ne peut pas être modifié tant qu’il est masqué.', ['motif' => $review->libelleMotifMasquage()]) }}
            </flux:callout.text>
        </flux:callout>
        <x-service-review :review="$review" :author="__('Vous')" />
    @else
        <x-tn.surface>
            <form wire:submit="save" class="space-y-6">
                <flux:radio.group wire:model="rating" :label="__('Votre note')" :description="__('De 1 (très insatisfait) à 5 (très satisfait).')">
                    @foreach (ServiceReview::RATING_LABELS as $note => $libelle)
                        <flux:radio :value="(string) $note" :label="$note.' — '.__($libelle)" />
                    @endforeach
                </flux:radio.group>

                <flux:textarea
                    wire:model="comment"
                    :label="__('Votre commentaire')"
                    :description="__('Racontez comment s’est passée votre démarche. Évitez les noms et coordonnées : votre avis est public (seuls votre prénom et l’initiale de votre nom sont affichés).')"
                    rows="5"
                    maxlength="{{ ServiceReview::COMMENT_MAX }}"
                    required
                />

                @error('throttle')
                    <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
                @enderror

                <x-envoi-deja-fait :le="$envoiDejaFaitLe" :url="$envoiDejaFaitUrl" message="Cet avis a déjà été envoyé" lien="voir mes avis" />

                <div class="flex flex-wrap items-center gap-2">
                    <x-submit-button variant="primary" icon="paper-airplane">{{ $review ? __('Mettre à jour mon avis') : __('Envoyer mon avis') }}</x-submit-button>
                    <flux:button variant="ghost" :href="route('services.show', $service)" wire:navigate>{{ __('Retour à la fiche') }}</flux:button>
                </div>
            </form>
        </x-tn.surface>
    @endif
</section>
