<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\ExplicationSimple;
use App\Services\Simplificateur;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
| F90 : bouton discret « Expliquer plus simplement » à côté d'un passage administratif.
| L'explication (rédigée à l'avance + synonymes en base + lexique D13) s'affiche sur place dans un encart repliable ;
| le reste de la page ne change pas. Aucune IA : App\Services\Simplificateur (SimplificateurRegles par défaut).
| Réservé aux personnes connectées (ExplicationSimplePolicy), limité à 20 demandes par minute et par utilisateur.
|
| Utilisation : <livewire:explication-simple cle="depot-demarche" /> (:avec-texte="false" si le passage est déjà dans la page)
*/
new class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public string $cle;

    #[Locked]
    public bool $disponible = false;

    /** Passage administratif affiché au-dessus du bouton (null : le passage est déjà écrit dans la page). */
    #[Locked]
    public ?string $texteOfficiel = null;

    #[Locked]
    public bool $ouvert = false;

    /** @var array{titre: string, explication: string, mots: list<array{mot: string, equivalent: string}>, termes: list<array{slug: string, terme: string, definition: string}>}|null */
    #[Locked]
    public ?array $resultat = null;

    public function mount(string $cle, bool $avecTexte = true): void
    {
        $this->cle = $cle;

        $passage = auth()->check()
            ? ExplicationSimple::query()->where('cle', $cle)->where('actif', true)->first(['texte_officiel'])
            : null;

        $this->disponible = $passage !== null;
        $texte = $passage?->texte_officiel;
        $this->texteOfficiel = $avecTexte && filled($texte) ? $texte : null;
    }

    public function basculer(): void
    {
        $passage = ExplicationSimple::query()->where('cle', $this->cle)->firstOrFail();
        $this->authorize('view', $passage);

        if ($this->ouvert) {
            $this->ouvert = false;

            return;
        }

        if ($this->resultat === null) {
            $this->throttlePerUser('explication-simple', 20, 60);

            $this->resultat = ['titre' => $passage->titre] + app(Simplificateur::class)->expliquer($passage);
        }

        $this->ouvert = true;
    }
}; ?>

<div class="my-2" data-test="explication-simple-{{ $cle }}">
    @if ($disponible)
        @if ($texteOfficiel)
            <p class="max-w-prose text-sm text-ink-2">{{ $texteOfficiel }}</p>
        @endif

        <button
            type="button"
            wire:click="basculer"
            aria-expanded="{{ $ouvert ? 'true' : 'false' }}"
            aria-controls="explication-{{ $cle }}-{{ $this->getId() }}"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-sm px-1 text-sm font-medium text-cyan underline decoration-dotted underline-offset-4 hover:text-ink focus-visible:outline-2 focus-visible:outline-cyan"
        >
            <flux:icon.light-bulb variant="micro" class="size-4" aria-hidden="true" />
            <span wire:loading.remove wire:target="basculer">{{ $ouvert ? __('Masquer l’explication') : __('Expliquer plus simplement') }}</span>
            <span wire:loading wire:target="basculer">{{ __('Chargement…') }}</span>
        </button>

        @error('throttle')
            <p class="mt-1 text-sm text-magenta" role="alert">{{ $message }}</p>
        @enderror

        <div id="explication-{{ $cle }}-{{ $this->getId() }}" aria-live="polite">
            @if ($ouvert && $resultat !== null)
                <section
                    aria-label="{{ __('Explication simple : :titre', ['titre' => $resultat['titre']]) }}"
                    class="mt-2 max-w-prose space-y-3 rounded-md border border-line border-s-4 border-s-cyan bg-surface-2 p-4 text-sm text-ink"
                >
                    <p class="font-semibold">{{ __('En mots simples') }}</p>
                    <p>{{ $resultat['explication'] }}</p>

                    @if (count($resultat['mots']))
                        <div>
                            <p class="font-semibold">{{ __('Mots difficiles du texte') }}</p>
                            <ul class="mt-1 list-disc space-y-1 ps-5">
                                @foreach ($resultat['mots'] as $mot)
                                    <li><span class="font-medium">« {{ $mot['mot'] }} »</span> {{ __('veut dire :') }} {{ $mot['equivalent'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (count($resultat['termes']))
                        <div>
                            <p class="font-semibold">{{ __('Mots utiles') }}</p>
                            <dl class="mt-1 space-y-1">
                                @foreach ($resultat['termes'] as $terme)
                                    <div>
                                        <dt class="inline font-medium">{{ $terme['terme'] }} :</dt>
                                        <dd class="inline">{{ $terme['definition'] }}</dd>
                                        <a href="{{ route('lexique') }}#terme-{{ $terme['slug'] }}" class="text-cyan underline underline-offset-4">{{ __('Lexique') }}<span class="sr-only"> : {{ $terme['terme'] }}</span></a>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    <p class="text-xs text-ink-2">{{ __('Cette explication aide à comprendre : le texte officiel fait foi.') }}</p>
                </section>
            @endif
        </div>
    @endif
</div>
