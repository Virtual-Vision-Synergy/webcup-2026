<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Service;
use App\Services\OrientationServices;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
| F92 : « Je ne sais pas à qui m'adresser ». L'habitant décrit son besoin en quelques mots ; le moteur D10
| (OrientationServices : mots-clés, synonymes et règles en base, éditables par l'admin) propose 1 à 3 services
| avec la raison. Une IA pourra être branchée derrière l'interface ReformulateurRequete ; si elle est absente ou
| tombe en panne, la recherche tolérante D10 prend le relais. Rien ne correspond : message à la mairie (D04) pré-rempli.
*/
new #[Title('Je ne sais pas à qui m\'adresser')] class extends Component {
    use ThrottlesPerUser;

    /** Nombre maximal de services proposés. */
    private const MAX_PROPOSITIONS = 3;

    public string $besoin = '';

    /**
     * Résultat de la dernière orientation (null tant que rien n'a été demandé).
     *
     * @var list<array{id: int, raisons: list<string>}>|null
     */
    #[Locked]
    public ?array $propositions = null;

    #[Locked]
    public string $besoinAnalyse = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Service::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'besoin' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['besoin' => 'description de votre besoin'];
    }

    public function orienter(): void
    {
        $this->authorize('viewAny', Service::class);

        $this->validate();

        $this->throttlePerUser('orientation', maxAttempts: 20, decaySeconds: 60);

        $this->besoinAnalyse = trim($this->besoin);
        $this->propositions = $this->propositionsPour($this->besoinAnalyse);
    }

    public function recommencer(): void
    {
        $this->authorize('viewAny', Service::class);

        $this->reset('besoin', 'propositions', 'besoinAnalyse');
    }

    /**
     * Seuls les services reconnus par au moins un mot de la demande (avec une raison) sont proposés :
     * les « plus proches » sans raison du moteur D10 ne sont pas une orientation fiable.
     *
     * @return list<array{id: int, raisons: list<string>}>
     */
    private function propositionsPour(string $besoin): array
    {
        $resultats = array_values(array_filter(
            app(OrientationServices::class)->rechercher($besoin)['resultats'],
            fn (array $resultat): bool => $resultat['raisons'] !== [],
        ));

        if ($resultats === []) {
            return [];
        }

        // On écarte les correspondances trop faibles face à la meilleure (un mot isolé de la description…).
        $seuil = (int) ceil($resultats[0]['score'] * 0.4);

        $retenus = array_filter($resultats, fn (array $resultat): bool => $resultat['score'] >= $seuil);

        return array_map(
            fn (array $resultat): array => ['id' => $resultat['id'], 'raisons' => array_slice($resultat['raisons'], 0, 3)],
            array_slice(array_values($retenus), 0, self::MAX_PROPOSITIONS),
        );
    }

    /**
     * Services proposés, dans l'ordre de pertinence.
     *
     * @return list<array{service: Service, raisons: list<string>}>
     */
    public function services(): array
    {
        $ids = array_column($this->propositions ?? [], 'id');
        $services = Service::query()->with('interruptionCourante')->whereIn('id', $ids)->get()->keyBy('id');

        $liste = [];

        foreach ($this->propositions ?? [] as $proposition) {
            $service = $services->get($proposition['id']);

            if ($service !== null) {
                $liste[] = ['service' => $service, 'raisons' => $proposition['raisons']];
            }
        }

        return $liste;
    }

    /**
     * Lien « Écrire à la mairie » (D04) avec le sujet et le besoin déjà saisis.
     */
    public function lienMairie(): string
    {
        return route('messages.create', [
            'sujet' => 'Je ne sais pas à quel service m\'adresser',
            'message' => Str::limit($this->besoinAnalyse, 1000, ''),
        ]);
    }

    /**
     * Lien « Commencer la démarche » avec le service et le besoin pré-remplis.
     */
    public function lienDemarche(Service $service): string
    {
        return route('demarches.create', [
            'service' => $service->id,
            'besoin' => Str::limit($this->besoinAnalyse, 1000, ''),
        ]);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Orientation"
        title="Je ne sais pas à qui m'adresser"
        subtitle="Décrivez votre besoin avec vos mots : nous vous indiquons le service compétent et la démarche à suivre."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Services' => route('services.index'), 'Orientation' => null]"
    />

    <x-tn.surface>
        <form wire:submit="orienter" class="space-y-4" novalidate>
            <flux:textarea
                wire:model="besoin"
                label="Décrivez votre besoin"
                description="Exemple : « les poubelles ne sont pas ramassées dans ma rue » ou « il me faut un acte de naissance pour mon fils »."
                rows="4"
                maxlength="1000"
                required
            />

            <flux:error name="throttle" />

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled" wire:target="orienter">
                    Trouver le bon service
                </flux:button>
                @if ($propositions !== null)
                    <flux:button variant="ghost" wire:click="recommencer">Recommencer</flux:button>
                @endif
                <span wire:loading wire:target="orienter" class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Recherche…</span>
            </div>
        </form>
    </x-tn.surface>

    @if ($propositions !== null)
        <div wire:loading.remove wire:target="orienter" class="space-y-4" aria-live="polite">
            @php($services = $this->services())

            @if ($services === [])
                <x-tn.surface class="space-y-3">
                    <flux:heading size="lg">Nous n'avons pas trouvé de service correspondant</flux:heading>
                    <flux:text>
                        Pas d'inquiétude : envoyez votre demande à la mairie, un agent la transmettra au bon service.
                        Votre message est déjà rédigé, il vous suffit de le relire.
                    </flux:text>
                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="primary" icon="envelope" :href="$this->lienMairie()" wire:navigate>
                            Envoyer ma demande à la mairie
                        </flux:button>
                        <flux:button variant="ghost" :href="route('services.index')" wire:navigate>Parcourir tous les services</flux:button>
                    </div>
                </x-tn.surface>
            @else
                <flux:heading size="lg">
                    {{ count($services) === 1 ? 'Le service qui peut vous aider' : 'Les services qui peuvent vous aider' }}
                </flux:heading>

                <ol class="grid gap-3">
                    @foreach ($services as $proposition)
                        @php($service = $proposition['service'])
                        <li wire:key="orientation-{{ $service->id }}">
                            <x-tn.surface class="flex flex-col gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">
                                        {{ $loop->iteration }} · {{ Service::labelCategorie($service->categorie) ?? 'Service municipal' }}
                                    </p>
                                    <h3 class="mt-1 font-medium text-ink">{{ $service->nom }}</h3>
                                    <p class="mt-1 text-sm text-ink-2">
                                        Parce que vous parlez de
                                        @foreach ($proposition['raisons'] as $raison)
                                            « {{ Str::lower($raison) }} »{{ $loop->last ? '.' : ($loop->remaining === 1 ? ' et' : ',') }}
                                        @endforeach
                                    </p>
                                    @if ($service->estIndisponible())
                                        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2">
                                            <flux:callout.text>{{ $service->messageIndisponibilite() }}</flux:callout.text>
                                        </flux:callout>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @unless ($service->estIndisponible())
                                        <flux:button size="sm" variant="primary" icon="arrow-right" :href="$this->lienDemarche($service)" wire:navigate>
                                            Commencer la démarche<span class="sr-only"> : {{ $service->nom }}</span>
                                        </flux:button>
                                    @endunless
                                    <flux:button size="sm" variant="ghost" :href="route('services.show', $service)" wire:navigate>
                                        Voir le service<span class="sr-only"> {{ $service->nom }}</span>
                                    </flux:button>
                                </div>
                            </x-tn.surface>
                        </li>
                    @endforeach
                </ol>

                <p class="text-sm text-ink-2">
                    Aucun ne correspond ?
                    <flux:link :href="$this->lienMairie()" wire:navigate>Envoyez votre demande à la mairie</flux:link>, elle sera orientée par un agent.
                </p>
            @endif
        </div>
    @endif
</section>
