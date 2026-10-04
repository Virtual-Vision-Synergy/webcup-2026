<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\ActionLog;
use App\Models\Consultation;
use App\Models\ParticipationConsultation;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * F65 : fiche d'une consultation. L'habitant concerné répond une seule fois (accusé de réception daté) ;
 * à la clôture, résultats et décision de la ville sont visibles par les participants (ConsultationPolicy::voirResultats).
 * Les agents voient le nombre de participants et publient la décision une fois la consultation close.
 */
new #[Title('Consultation')] class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public Consultation $record;

    public string $choix = '';

    public string $decision = '';

    public function mount(Consultation $consultation): void
    {
        $this->authorize('view', $consultation);
        $this->record = $consultation->load('quartier:id,nom');
        $this->decision = (string) $consultation->decision;
    }

    /**
     * Participation de l'utilisateur connecté (jamais celle d'un autre : filtrée par user_id).
     */
    #[Computed]
    public function maParticipation(): ?ParticipationConsultation
    {
        return $this->record->participationDe(auth()->user());
    }

    #[Computed]
    public function nombreParticipants(): int
    {
        return $this->record->participations()->count();
    }

    public function repondre(): void
    {
        $this->authorize('repondre', $this->record);
        $this->throttlePerUser('consultation-reponse', maxAttempts: 10, decaySeconds: 60);

        $validated = $this->validate(
            ['choix' => ['required', 'integer', Rule::in(array_keys($this->record->listeOptions()))]],
            ['choix.required' => __('Choisissez une réponse.'), 'choix.in' => __('Choisissez une réponse.')],
        );

        $participation = new ParticipationConsultation;
        $participation->choix = (int) $validated['choix'];
        $participation->consultation()->associate($this->record);
        $participation->user()->associate(auth()->user());

        try {
            $participation->save();
        } catch (UniqueConstraintViolationException) {
            unset($this->maParticipation);
            Flux::toast(variant: 'warning', text: __('Vous avez déjà participé à cette consultation.'));

            return;
        }

        ActionLog::record('consultation_reponse', $this->record);
        unset($this->maParticipation, $this->nombreParticipants);

        Flux::toast(variant: 'success', text: __('Votre participation a bien été enregistrée le :date.', ['date' => $participation->participeLe()]));
    }

    public function publierDecision(): void
    {
        $this->authorize('publierDecision', $this->record);

        $validated = $this->validate(
            ['decision' => ['required', 'string', 'max:5000']],
            ['decision.required' => __('Décrivez la décision prise par la ville.')],
        );

        // Champs réservés hors #[Fillable] : assignés ici, après autorisation.
        $this->record->decision = trim($validated['decision']);
        $this->record->decision_le = now();
        $this->record->save();

        ActionLog::record('consultation_decision', $this->record);

        Flux::toast(variant: 'success', text: __('Décision publiée : les participants la voient sur la consultation.'));
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        :label="__('Consultation').' · '.$record->nomPublic()"
        :title="$record->question"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Consultations' => route('consultations.index'), 'Consultation' => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-tn.status-badge :etat="$record->statutBadge()">{{ $record->statutLabel() }}</x-tn.status-badge>
                <span class="font-mono text-xs text-ink-2">{{ __('Du :debut au :fin', ['debut' => $record->ouvertureLocale(), 'fin' => $record->clotureLocale()]) }}</span>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-4">{{ __('Explication') }}</x-tn.section-label>
                <p class="whitespace-pre-line leading-7 text-ink">{{ $record->explication }}</p>
            </x-tn.surface>

            {{-- Participation de l'habitant : accusé de réception ou formulaire. --}}
            @if ($this->maParticipation)
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>{{ __('Vous avez participé le :date.', ['date' => $this->maParticipation->participeLe()]) }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Votre réponse : « :option ». Elle a bien été prise en compte.', ['option' => $record->listeOptions()[$this->maParticipation->choix] ?? '—']) }}
                        @unless ($record->estCloturee())
                            {{ __('Les résultats et la décision de la ville seront publiés ici après la clôture, le :date.', ['date' => $record->clotureLocale()]) }}
                        @endunless
                    </flux:callout.text>
                </flux:callout>
            @elseif (auth()->user()->can('repondre', $record))
                <x-tn.surface id="repondre">
                    <x-tn.section-label as="h2" class="mb-2">{{ __('Votre avis') }}</x-tn.section-label>
                    <p class="mb-4 text-sm text-ink-2">{{ __('Vous ne pouvez répondre qu’une seule fois. Votre réponse n’est pas affichée publiquement.') }}</p>

                    <form wire:submit="repondre" class="space-y-4">
                        <fieldset data-requis>
                            <legend class="sr-only">{{ $record->question }}</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($record->listeOptions() as $index => $option)
                                    <label wire:key="option-{{ $index }}" class="flex cursor-pointer items-center gap-3 rounded-md border border-line p-3 text-sm has-[:checked]:border-cyan has-[:checked]:ring-2 has-[:checked]:ring-cyan">
                                        <input type="radio" wire:model="choix" name="choix" value="{{ $index }}" required class="size-4 accent-[var(--color-cyan)]">
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <flux:error name="choix" class="mt-2" />
                        </fieldset>

                        <flux:error name="throttle" />

                        <flux:button type="submit" variant="primary" wire:confirm="{{ __('Confirmer votre réponse ? Elle ne pourra plus être modifiée.') }}">
                            <span wire:loading.remove wire:target="repondre">{{ __('Envoyer ma réponse') }}</span>
                            <span wire:loading wire:target="repondre">{{ __('Enregistrement…') }}</span>
                        </flux:button>
                    </form>
                </x-tn.surface>
            @elseif ($record->statut() === 'a_venir')
                <flux:callout icon="clock">
                    <flux:callout.heading>{{ __('La consultation ouvrira le :date.', ['date' => $record->ouvertureLocale()]) }}</flux:callout.heading>
                </flux:callout>
            @elseif ($record->estCloturee() && auth()->user()->cannot('voirResultats', $record))
                <flux:callout icon="lock-closed">
                    <flux:callout.heading>{{ __('Cette consultation est close.') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Les résultats et la décision sont publiés aux habitants qui ont participé.') }}</flux:callout.text>
                </flux:callout>
            @elseif ($record->estOuverte() && ! auth()->user()->isCitoyen())
                <flux:text>{{ __('Seuls les habitants peuvent répondre à une consultation.') }}</flux:text>
            @endif

            {{-- Résultats et décision : participants après la clôture, agents et admins à tout moment. --}}
            @can('voirResultats', $record)
                @php($repartition = $record->repartition())
                <x-tn.surface>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <x-tn.section-label as="h2">{{ $record->estCloturee() ? __('Résultats') : __('Résultats provisoires (agents)') }}</x-tn.section-label>
                        <span class="font-mono text-sm text-ink">{{ trans_choice(':count participant|:count participants', $this->nombreParticipants, ['count' => $this->nombreParticipants]) }}</span>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($repartition as $index => $ligne)
                            <li wire:key="resultat-{{ $index }}">
                                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                    <span @class(['text-ink', 'font-semibold' => $this->maParticipation?->choix === $index])>
                                        {{ $ligne['option'] }}
                                        @if ($this->maParticipation?->choix === $index)
                                            <span class="text-xs font-normal text-cyan">{{ __('(votre réponse)') }}</span>
                                        @endif
                                    </span>
                                    <span class="font-mono text-ink-2">{{ $ligne['total'] }} · {{ $ligne['pourcentage'] }} %</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $ligne['pourcentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $ligne['option'] }}">
                                    <div class="h-full rounded-full bg-cyan" style="width: {{ $ligne['pourcentage'] }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-tn.surface>

                @if ($record->estCloturee())
                    <x-tn.surface>
                        <x-tn.section-label as="h2" class="mb-4">{{ __('Décision de la ville') }}</x-tn.section-label>
                        @if ($record->decision)
                            <p class="whitespace-pre-line leading-7 text-ink">{{ $record->decision }}</p>
                            <p class="mt-2 font-mono text-xs text-ink-2">{{ __('Publiée le :date', ['date' => \App\Models\Remontee::dateLocale($record->decision_le)]) }}</p>
                        @else
                            <p class="text-ink-2">{{ __('La ville étudie les résultats : la décision sera publiée ici.') }}</p>
                        @endif

                        @can('publierDecision', $record)
                            <form wire:submit="publierDecision" class="mt-4 space-y-3 border-t border-line pt-4">
                                <flux:textarea wire:model="decision" :label="$record->decision ? __('Modifier la décision') : __('Publier la décision')" rows="4" maxlength="5000" required />
                                <flux:button type="submit" variant="primary">
                                    <span wire:loading.remove wire:target="publierDecision">{{ __('Publier la décision') }}</span>
                                    <span wire:loading wire:target="publierDecision">{{ __('Publication…') }}</span>
                                </flux:button>
                            </form>
                        @endcan
                    </x-tn.surface>
                @endif
            @endcan
        </div>

        <div class="space-y-6">
            <x-tn.panel label="{{ __('En bref') }}" padding="p-5 md:p-6">
                <dl>
                    <x-tn.field label="{{ __('Statut') }}">
                        <x-tn.status-badge :etat="$record->statutBadge()">{{ $record->statutLabel() }}</x-tn.status-badge>
                    </x-tn.field>
                    <x-tn.field label="{{ __('Public') }}"><p class="text-sm">{{ $record->nomPublic() }}</p></x-tn.field>
                    <x-tn.field label="{{ __('Ouverture') }}"><p class="font-mono text-sm">{{ $record->ouvertureLocale() }}</p></x-tn.field>
                    <x-tn.field label="{{ __('Clôture') }}"><p class="font-mono text-sm">{{ $record->clotureLocale() }}</p></x-tn.field>
                    @can('create', \App\Models\Consultation::class)
                        <x-tn.field label="{{ __('Participants') }}"><p class="font-mono text-sm">{{ $this->nombreParticipants }}</p></x-tn.field>
                    @endcan
                    <x-tn.field label="{{ __('Options de réponse') }}">
                        <ul class="list-inside list-disc text-sm">
                            @foreach ($record->listeOptions() as $option)
                                <li>{{ $option }}</li>
                            @endforeach
                        </ul>
                    </x-tn.field>
                </dl>
            </x-tn.panel>
        </div>
    </div>
</section>
