<?php

use App\Models\Onboarding;
use App\Services\OnboardingProgress;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bienvenue à Nova Terra')] class extends Component {
    public function mount(): void
    {
        $this->authorize('view', Onboarding::class);

        $this->progress->synchroniser();

        // Les pages ouvertes depuis le parcours y ramènent l'habitant (bandeau, redirection après enregistrement).
        $this->progress->estTermine()
            ? session()->forget(OnboardingProgress::SESSION_DEPUIS_PARCOURS)
            : session()->put(OnboardingProgress::SESSION_DEPUIS_PARCOURS, true);
    }

    /**
     * Toujours le parcours de l'utilisateur connecté : aucun identifiant ne vient du navigateur.
     */
    #[Computed]
    public function progress(): OnboardingProgress
    {
        return OnboardingProgress::pour(auth()->user());
    }

    public function passer(): void
    {
        $this->authorize('avancer', Onboarding::class);

        $this->progress->passer();
        session()->forget(OnboardingProgress::SESSION_DEPUIS_PARCOURS);

        Flux::toast(text: __('Prise en main passée. Vous pourrez la revoir depuis votre profil.'));

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

@php
    $progress = $this->progress;
    $termine = $progress->estTermine();
    $courante = $progress->etapeCourante();
    $prenom = str(auth()->user()->name)->before(' ');
@endphp

<section class="mx-auto w-full max-w-4xl space-y-6">
    @if ($termine)
        {{-- FÉLICITATIONS --}}
        <x-tn.page-header label="{{ __('Prise en main terminée') }}" :title="__('Bravo :prenom, vous êtes prêt·e !', ['prenom' => $prenom])" subtitle="{{ __('Votre profil est complet, vous connaissez les services de la ville et votre première démarche est déposée.') }}" />

        <x-tn.panel padding="p-5 md:p-6">
            <x-onboarding.progression :progress="$progress" />
        </x-tn.panel>

        <x-tn.surface>
            <h2 class="tn-display text-lg font-semibold text-ink">{{ __('Et maintenant ?') }}</h2>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ([
                    ['Suivre mes démarches', 'demarches.index', 'file-text'],
                    ['Mon espace', 'dashboard', 'layout-grid'],
                    ['Les services de la ville', 'services.index', 'landmark'],
                    ['Les actualités', 'actualites.index', 'newspaper'],
                ] as [$libelle, $route, $icone])
                    @if (Route::has($route))
                        <li wire:key="lien-{{ $route }}">
                            <a href="{{ route($route) }}" wire:navigate class="flex min-h-12 items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 font-medium text-ink transition-colors hover:border-cyan/40 hover:text-cyan">
                                <flux:icon :name="$icone" class="size-5 text-cyan" />
                                {{ __($libelle) }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </x-tn.surface>
    @else
        {{-- PARCOURS --}}
        <x-tn.page-header
            label="{{ __('Bienvenue à Nova Terra') }}"
            :breadcrumb="['Mon espace' => route('dashboard'), 'Bienvenue' => null]"
            :title="__('Bonjour :prenom, bienvenue chez vous !', ['prenom' => $prenom])"
            subtitle="{{ __('Votre mairie est désormais en ligne. Trois étapes simples pour bien démarrer : comptez cinq minutes.') }}"
        />

        <x-tn.panel padding="p-5 md:p-6">
            <x-onboarding.progression :progress="$progress" />
        </x-tn.panel>

        <ol class="space-y-3">
            @foreach ($progress->etapes() as $index => $etape)
                @php
                    $numero = $index + 1;
                    $enCours = $numero === $courante;
                @endphp
                <li wire:key="etape-{{ $etape['cle'] }}">
                    <article @class([
                        'flex flex-col gap-4 rounded-md border p-5 md:flex-row md:items-center',
                        'border-cyan bg-cyan/5 shadow-sm' => $enCours,
                        'border-line bg-surface' => ! $enCours,
                    ])>
                        <span @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-full border font-mono text-sm font-semibold',
                            'border-green/40 bg-green/10 text-green' => $etape['faite'],
                            'border-cyan bg-cyan text-on-cyan' => $enCours,
                            'border-line text-ink-2' => ! $etape['faite'] && ! $enCours,
                        ]) aria-hidden="true">
                            @if ($etape['faite'])
                                <flux:icon name="check" class="size-5" />
                            @else
                                {{ $numero }}
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <h2 class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                <span>Étape {{ $numero }} · {{ $etape['titre'] }}</span>
                                <span @class([
                                    'rounded-xs border px-2 py-0.5 font-mono text-[0.65625rem] uppercase tracking-[.06em]',
                                    'border-green/40 text-green' => $etape['faite'],
                                    'border-cyan text-cyan' => $enCours,
                                    'border-line text-ink-2' => ! $etape['faite'] && ! $enCours,
                                ])>{{ $etape['faite'] ? __('Faite') : ($enCours ? __('En cours') : __('À faire')) }}</span>
                            </h2>
                            <p class="mt-1 text-ink-2">{{ $etape['texte'] }}</p>
                        </div>

                        @if ($etape['faite'])
                            <flux:button :href="$etape['url']" wire:navigate variant="ghost" size="sm">{{ __('Revoir') }}</flux:button>
                        @else
                            <flux:button
                                :href="$etape['url']"
                                wire:navigate
                                :variant="$enCours ? 'primary' : 'filled'"
                                icon:trailing="arrow-right"
                                data-test="etape-{{ $etape['cle'] }}"
                            >{{ $etape['action'] }}</flux:button>
                        @endif
                    </article>
                </li>
            @endforeach
        </ol>

        @unless ($progress->estPasse())
            <div class="flex flex-col items-start gap-2 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-ink-2">{{ __('Pas le temps maintenant ? Le parcours reste accessible depuis votre profil.') }}</p>
                <flux:button variant="ghost" wire:click="passer" data-test="onboarding-passer">
                    <span wire:loading.remove wire:target="passer">{{ __('Passer pour l\'instant') }}</span>
                    <span wire:loading wire:target="passer">{{ __('Un instant…') }}</span>
                </flux:button>
            </div>
        @endunless
    @endif
</section>
