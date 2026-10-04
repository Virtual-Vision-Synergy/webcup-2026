<?php

use App\Concerns\ExportsCsv;
use App\Concerns\ThrottlesPerUser;
use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\ExportPreset;
use App\Services\AuditLogger;
use App\Services\ExportDemarches;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * F88 : export personnalisé des demandes des habitants (CSV compatible Excel ou JSON).
 * Réservé aux agents et admins (ExportPresetPolicy::export, vérifiée dans chaque action).
 * Filtres, colonnes et format passent par la liste blanche d'ExportDemarches ; chaque téléchargement est journalisé (F47).
 */
new #[Layout('layouts::agent'), Title('Espace agent — Export des données de suivi')] class extends Component {
    use ExportsCsv, ThrottlesPerUser;

    public string $periode = '7j';

    public string $du = '';

    public string $au = '';

    public string $service = '';

    public string $statut = '';

    public string $priorite = '';

    /** @var array<int, string> */
    public array $colonnes = ExportDemarches::COLONNES_PAR_DEFAUT;

    public string $format = 'csv';

    public string $nomPrereglage = '';

    /** @var array{total: int, entetes: list<string>, lignes: list<array<string, string|bool|null>>}|null */
    public ?array $apercu = null;

    public function mount(): void
    {
        $this->authorize('export', ExportPreset::class);
    }

    /**
     * Tout changement de filtre, de colonne ou de format invalide l'aperçu affiché.
     */
    public function updated(string $propriete): void
    {
        if ($propriete !== 'nomPrereglage') {
            $this->apercu = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'periode' => ['nullable', Rule::in(array_keys(ExportDemarches::PERIODES))],
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
            'service' => ['nullable', Rule::in($this->servicesDisponibles->keys()->map(fn (int $id): string => (string) $id)->all())],
            'statut' => ['nullable', Rule::in(Demarche::STATUT_OPTIONS)],
            'priorite' => ['nullable', Rule::in(Demarche::PRIORITE_OPTIONS)],
            'format' => ['required', Rule::in(array_keys(ExportDemarches::FORMATS))],
            'colonnes' => ['array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['du' => 'date de début', 'au' => 'date de fin', 'service' => 'service', 'statut' => 'statut', 'priorite' => 'priorité', 'format' => 'format'];
    }

    /**
     * @return SupportCollection<int, string>
     */
    #[Computed]
    public function servicesDisponibles(): SupportCollection
    {
        $this->authorize('export', ExportPreset::class);

        return ExportDemarches::servicesPour(auth()->user());
    }

    /**
     * @return Collection<int, ExportPreset>
     */
    #[Computed]
    public function prereglages(): Collection
    {
        $this->authorize('viewAny', ExportPreset::class);

        return ExportPreset::query()->de(auth()->user())->orderBy('name')->get();
    }

    /**
     * Nombre exact de lignes et premières lignes, sans téléchargement ni journalisation.
     */
    public function previsualiser(): void
    {
        $this->authorize('export', ExportPreset::class);

        [$filtres, $colonnes] = $this->parametresValides();

        $query = ExportDemarches::requete(auth()->user(), $filtres);

        $this->apercu = [
            'total' => (clone $query)->count(),
            'entetes' => array_map(fn (string $cle): string => ExportDemarches::COLONNES[$cle], $colonnes),
            'lignes' => (clone $query)->orderBy('id')->limit(ExportDemarches::LIGNES_APERCU)->get()
                ->map(fn (Demarche $demarche): array => ExportDemarches::ligne($demarche, $colonnes))
                ->all(),
        ];
    }

    public function telecharger(): StreamedResponse
    {
        $this->authorize('export', ExportPreset::class);

        [$filtres, $colonnes] = $this->parametresValides();

        return $this->exporter($filtres, $colonnes, $this->format);
    }

    public function enregistrerPrereglage(): void
    {
        $this->authorize('create', ExportPreset::class);

        $this->validate(['nomPrereglage' => ['required', 'string', 'max:80']], [], ['nomPrereglage' => 'nom du préréglage']);
        [$filtres, $colonnes] = $this->parametresValides();

        $preset = new ExportPreset(['name' => trim($this->nomPrereglage)]);
        $preset->user()->associate(auth()->user());
        $preset->filters = $filtres;
        $preset->columns = $colonnes;
        $preset->format = ExportDemarches::nettoyerFormat($this->format);
        $preset->save();

        $this->reset('nomPrereglage');
        unset($this->prereglages);

        Flux::toast(variant: 'success', text: 'Préréglage « '.$preset->name.' » enregistré.');
    }

    /**
     * Export en un clic : la période relative (« 7 derniers jours ») est recalculée aujourd'hui.
     */
    public function exporterPrereglage(int $id): StreamedResponse
    {
        $this->authorize('export', ExportPreset::class);
        $preset = ExportPreset::findOrFail($id);
        $this->authorize('view', $preset);

        $filtres = ExportDemarches::nettoyerFiltres($preset->filters);
        $colonnes = ExportDemarches::nettoyerColonnes($preset->columns) ?: ExportDemarches::COLONNES_PAR_DEFAUT;

        return $this->exporter($filtres, $colonnes, ExportDemarches::nettoyerFormat($preset->format), $preset);
    }

    /**
     * Recharge un préréglage dans le formulaire (pour le modifier ou le prévisualiser).
     */
    public function chargerPrereglage(int $id): void
    {
        $preset = ExportPreset::findOrFail($id);
        $this->authorize('view', $preset);

        $filtres = ExportDemarches::nettoyerFiltres($preset->filters);
        $this->fill($filtres);
        $this->colonnes = ExportDemarches::nettoyerColonnes($preset->columns) ?: ExportDemarches::COLONNES_PAR_DEFAUT;
        $this->format = ExportDemarches::nettoyerFormat($preset->format);
        $this->apercu = null;
        $this->resetValidation();
    }

    public function supprimerPrereglage(int $id): void
    {
        $preset = ExportPreset::findOrFail($id);
        $this->authorize('delete', $preset);

        $preset->delete();
        unset($this->prereglages);

        Flux::toast(variant: 'success', text: 'Préréglage supprimé.');
    }

    /**
     * Valide la saisie puis la réduit à la liste blanche (une colonne inconnue est ignorée).
     *
     * @return array{0: array{periode: string, du: string, au: string, service: string, statut: string, priorite: string}, 1: list<string>}
     */
    private function parametresValides(): array
    {
        $this->validate();

        $colonnes = ExportDemarches::nettoyerColonnes($this->colonnes);

        if ($colonnes === []) {
            throw ValidationException::withMessages(['colonnes' => 'Choisissez au moins une colonne à exporter.']);
        }

        return [ExportDemarches::nettoyerFiltres($this->only(['periode', 'du', 'au', 'service', 'statut', 'priorite'])), $colonnes];
    }

    /**
     * Téléchargement en flux (lecture par paquets), limité par utilisateur et journalisé (qui, quand, quoi — jamais le contenu).
     *
     * @param  array{periode: string, du: string, au: string, service: string, statut: string, priorite: string}  $filtres
     * @param  list<string>  $colonnes
     */
    private function exporter(array $filtres, array $colonnes, string $format, ?ExportPreset $preset = null): StreamedResponse
    {
        $this->throttlePerUser('export-demandes', maxAttempts: 10, decaySeconds: 60);

        $query = ExportDemarches::requete(auth()->user(), $filtres);
        $total = (clone $query)->count();

        AuditLogger::log('exported', new Demarche, [
            'format' => ['avant' => null, 'apres' => strtoupper($format)],
            'filtres' => ['avant' => null, 'apres' => ExportDemarches::resumeFiltres($filtres)],
            'colonnes' => ['avant' => null, 'apres' => implode(', ', $colonnes)],
            'lignes' => ['avant' => null, 'apres' => $total],
            'prereglage' => ['avant' => null, 'apres' => $preset?->name ?? 'aucun'],
        ], 'Export', 'Export des demandes ('.strtoupper($format).', '.$total.' ligne(s))');

        if ($format === 'json') {
            return response()->streamDownload(function () use ($query, $colonnes): void {
                echo '[';
                $premiere = true;

                foreach ($query->lazyById(200) as $demarche) {
                    echo ($premiere ? '' : ',')."\n".json_encode(ExportDemarches::ligne($demarche, $colonnes, pourJson: true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    $premiere = false;
                }

                echo "\n]\n";
            }, 'export-demandes-'.now(AuditLog::FUSEAU)->format('Y-m-d').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        $colonnesCsv = [];
        foreach ($colonnes as $cle) {
            $colonnesCsv[ExportDemarches::COLONNES[$cle]] = fn (Demarche $demarche): mixed => ExportDemarches::ligne($demarche, [$cle])[$cle];
        }

        return $this->streamCsv('export', ExportPreset::class, $query, $colonnesCsv, 'export-demandes', dateFormat: 'Y-m-d');
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Export des données' => null]"
        title="Export des données de suivi"
        subtitle="Choisissez les demandes et les colonnes à transmettre, vérifiez l’aperçu puis téléchargez en CSV (Excel) ou JSON."
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="previsualiser" class="space-y-6 lg:col-span-2">
            {{-- Filtres --}}
            <flux:card class="space-y-4">
                <flux:heading size="lg">1. Quelles demandes ?</flux:heading>

                <flux:select wire:model.live="periode" label="Période (date de dépôt)">
                    @foreach (ExportDemarches::PERIODES as $valeur => $libelle)
                        <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($periode === '')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input type="date" wire:model.live="du" label="Du" />
                        <flux:input type="date" wire:model.live="au" label="Au" />
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:select wire:model.live="service" label="Service">
                        <flux:select.option value="">Tous mes services</flux:select.option>
                        @foreach ($this->servicesDisponibles as $id => $nom)
                            <flux:select.option :value="(string) $id">{{ $nom }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="statut" label="Statut">
                        <flux:select.option value="">Tous</flux:select.option>
                        @foreach (Demarche::STATUT_OPTIONS as $option)
                            <flux:select.option :value="$option">{{ Demarche::libelleStatut($option) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="priorite" label="Priorité">
                        <flux:select.option value="">Toutes</flux:select.option>
                        @foreach (array_reverse(Demarche::PRIORITE_OPTIONS) as $option)
                            <flux:select.option :value="$option">{{ Demarche::libellePriorite($option) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </flux:card>

            {{-- Colonnes (liste blanche) --}}
            <flux:card class="space-y-4">
                <flux:heading size="lg">2. Quelles informations ?</flux:heading>

                <flux:checkbox.group wire:model.live="colonnes" class="grid gap-2 sm:grid-cols-2">
                    @foreach (ExportDemarches::COLONNES as $cle => $libelle)
                        @if ($cle === 'demandeur_anonyme')
                            <div class="rounded-md border border-line p-3 sm:col-span-2">
                                <flux:checkbox value="demandeur_anonyme" label="Inclure un identifiant anonymisé du demandeur" description="Code stable (ex. HAB-3f9a…) pour regrouper les demandes d’une même personne sans l’identifier." />
                            </div>
                        @else
                            <flux:checkbox :value="$cle" :label="$libelle" />
                        @endif
                    @endforeach
                </flux:checkbox.group>
                @error('colonnes') <flux:text class="text-sm text-red-600">{{ $message }}</flux:text> @enderror

                <flux:text class="flex items-start gap-2 text-sm">
                    <flux:icon.shield-check variant="micro" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    Les données personnelles des habitants (nom, e-mail, téléphone, adresse, quartier, situation) ne sont jamais exportées.
                </flux:text>
            </flux:card>

            {{-- Format --}}
            <flux:card class="space-y-4">
                <flux:heading size="lg">3. Quel format ?</flux:heading>
                <flux:radio.group wire:model.live="format" variant="segmented">
                    @foreach (ExportDemarches::FORMATS as $valeur => $libelle)
                        <flux:radio :value="$valeur" :label="$libelle" />
                    @endforeach
                </flux:radio.group>

                @error('throttle') <flux:text class="text-sm text-red-600">{{ $message }}</flux:text> @enderror

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" icon="eye">Aperçu</flux:button>
                    @if ($apercu !== null && $apercu['total'] > 0)
                        <flux:button variant="primary" icon="arrow-down-tray" wire:click="telecharger">Télécharger ({{ $apercu['total'] }} ligne(s))</flux:button>
                    @endif
                    <span wire:loading class="self-center font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Préparation…</span>
                </div>
            </flux:card>
        </form>

        {{-- Préréglages --}}
        <aside class="space-y-4">
            <flux:card class="space-y-4">
                <flux:heading size="lg">Mes préréglages</flux:heading>

                <form wire:submit="enregistrerPrereglage" class="space-y-2">
                    <flux:input wire:model="nomPrereglage" label="Enregistrer les choix actuels" placeholder="Rapport hebdo transports" maxlength="80" />
                    <flux:button type="submit" size="sm" icon="bookmark">Enregistrer comme préréglage</flux:button>
                </form>

                @if ($this->prereglages->isEmpty())
                    <flux:text class="text-sm">Aucun préréglage. Enregistrez vos choix pour refaire cet export en un clic.</flux:text>
                @else
                    <ul class="space-y-3">
                        @foreach ($this->prereglages as $preset)
                            <li wire:key="preset-{{ $preset->id }}" class="space-y-2 rounded-md border border-line p-3">
                                <div>
                                    <p class="font-medium text-ink">{{ $preset->name }}</p>
                                    <p class="text-xs text-ink-2">{{ strtoupper($preset->format) }} · {{ ExportDemarches::resumeFiltres(ExportDemarches::nettoyerFiltres($preset->filters), $this->servicesDisponibles) }}</p>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <flux:button size="xs" variant="primary" icon="arrow-down-tray" wire:click="exporterPrereglage({{ $preset->id }})">Exporter</flux:button>
                                    <flux:button size="xs" icon="pencil-square" wire:click="chargerPrereglage({{ $preset->id }})">Charger</flux:button>
                                    <flux:button size="xs" variant="danger" icon="trash" wire:click="supprimerPrereglage({{ $preset->id }})" wire:confirm="Supprimer ce préréglage ?">Supprimer</flux:button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>
        </aside>
    </div>

    {{-- Aperçu --}}
    @if ($apercu !== null)
        <flux:card class="space-y-4">
            <flux:heading size="lg">Aperçu : {{ $apercu['total'] }} ligne(s) seront exportées</flux:heading>

            @if ($apercu['total'] === 0)
                <x-tn.empty icon="inbox" title="Aucune demande à exporter" text="Aucune demande de vos services ne correspond à ces filtres." />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-start">
                                @foreach ($apercu['entetes'] as $entete)
                                    <th scope="col" class="px-2 py-2 text-start font-medium text-ink">{{ $entete }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($apercu['lignes'] as $ligne)
                                <tr class="border-b border-line/50">
                                    @foreach ($ligne as $valeur)
                                        <td class="px-2 py-2 text-ink-2">{{ $valeur ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($apercu['total'] > count($apercu['lignes']))
                    <flux:text class="text-sm">… et {{ $apercu['total'] - count($apercu['lignes']) }} autre(s) ligne(s) dans le fichier.</flux:text>
                @endif
            @endif
        </flux:card>
    @endif
</section>
