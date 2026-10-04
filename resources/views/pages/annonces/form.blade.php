<?php

use App\Models\ActionLog;
use App\Models\Annonce;
use App\Models\Quartier;
use App\Services\NotifierAnnonce;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Message général')] class extends Component {
    #[Locked]
    public ?Annonce $record = null;

    public string $titre = '';
    public string $contenu = '';
    public string $niveau = 'information';

    /** Quartier ciblé (F29) : '' = toute la ville. */
    public string $quartier_id = '';

    /** Consignes à suivre, une par ligne (F29). */
    public string $consignes = '';

    /** Dates saisies en heure de Madagascar (Annonce::FUSEAU), converties en UTC à l'enregistrement. */
    public string $debut = '';
    public string $fin = '';

    /** F73 : message officiel du Haut Conseil (administrateurs uniquement, revérifié à l'enregistrement). */
    public bool $officiel = false;

    /** F89 : version en langage clair rédigée par l'agent, publiée seulement une fois validée. */
    public string $langage_clair = '';

    public bool $langageClairValide = false;

    public function mount(?Annonce $annonce = null): void
    {
        if ($annonce?->exists) {
            $this->authorize('update', $annonce);
            $this->record = $annonce;
            $this->titre = $annonce->titre;
            $this->contenu = $annonce->contenu;
            $this->niveau = $annonce->niveau;
            $this->quartier_id = (string) ($annonce->quartier_id ?? '');
            $this->consignes = (string) $annonce->consignes;
            $this->officiel = $annonce->estOfficiel();
            $this->langage_clair = (string) $annonce->langage_clair;
            $this->langageClairValide = $annonce->langageClairPublie();
            $this->debut = $annonce->debut->timezone(Annonce::FUSEAU)->format('Y-m-d\TH:i');
            $this->fin = $annonce->fin->timezone(Annonce::FUSEAU)->format('Y-m-d\TH:i');
        } else {
            $this->authorize('create', Annonce::class);
            // Pré-rempli pour publier en quelques secondes : maintenant → +24 h.
            $maintenant = now(Annonce::FUSEAU);
            $this->debut = $maintenant->format('Y-m-d\TH:i');
            $this->fin = $maintenant->addDay()->format('Y-m-d\TH:i');

            // F101 : /agent/annonces/create?modele=panne-electrique arrive pré-rempli.
            $modele = request()->query('modele');
            if (is_string($modele) && array_key_exists($modele, Annonce::MODELES)) {
                $this->appliquerModele($modele);
            }
        }
    }

    /**
     * F101 : pré-remplit le formulaire avec un modèle d'alerte (rien n'est publié avant « Publier »).
     */
    public function appliquerModele(string $cle): void
    {
        $this->authorize('create', Annonce::class);

        $champs = Annonce::depuisModele($cle);
        if ($this->record !== null || $champs === null) {
            return;
        }

        $this->titre = $champs['titre'];
        $this->contenu = $champs['contenu'];
        $this->consignes = $champs['consignes'];
        $this->niveau = $champs['niveau'];
        $this->quartier_id = (string) ($champs['quartier_id'] ?? '');
        $this->debut = $champs['debut']->format('Y-m-d\TH:i');
        $this->fin = $champs['fin']->format('Y-m-d\TH:i');
        $this->resetValidation();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:120'],
            'contenu' => ['required', 'string', 'max:2000'],
            'niveau' => ['required', Rule::in(Annonce::NIVEAU_OPTIONS)],
            'quartier_id' => ['nullable', 'integer', 'exists:quartiers,id'],
            'consignes' => ['nullable', 'string', 'max:2000'],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
            'officiel' => ['boolean'],
            'langage_clair' => ['nullable', 'required_if_accepted:langageClairValide', 'string', 'max:2000'],
            'langageClairValide' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'titre.required' => 'Indiquez un titre.',
            'titre.max' => 'Le titre ne doit pas dépasser 120 caractères.',
            'contenu.required' => 'Indiquez le contenu du message.',
            'contenu.max' => 'Le contenu ne doit pas dépasser 2000 caractères.',
            'niveau.required' => 'Choisissez un niveau de gravité.',
            'niveau.in' => 'Ce niveau de gravité n’existe pas.',
            'quartier_id.integer' => 'Ce quartier n’existe pas.',
            'quartier_id.exists' => 'Ce quartier n’existe pas.',
            'consignes.max' => 'Les consignes ne doivent pas dépasser 2000 caractères.',
            'debut.required' => 'Indiquez la date de début de diffusion.',
            'debut.date' => 'La date de début n’est pas valide.',
            'fin.required' => 'Indiquez la date de fin de diffusion.',
            'fin.date' => 'La date de fin n’est pas valide.',
            'fin.after' => 'La fin de diffusion doit être après le début.',
            'langage_clair.required_if_accepted' => 'Rédigez la version en langage clair avant de la valider.',
            'langage_clair.max' => 'La version en langage clair ne doit pas dépasser 2000 caractères.',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    #[Computed]
    public function quartiers(): \Illuminate\Support\Collection
    {
        return Quartier::query()->orderBy('nom')->pluck('nom', 'id');
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Annonce::class);

        $validated = $this->validate();
        unset($validated['officiel'], $validated['langageClairValide']);

        // F89 : la date de validation est conservée tant que le texte validé ne change pas.
        $validated['langage_clair'] = trim((string) ($validated['langage_clair'] ?? '')) ?: null;
        $dejaValide = (bool) $this->record?->langageClairPublie() && $this->record->langage_clair === $validated['langage_clair'];

        // F73 : la mention officielle ne s'ajoute ni ne se retire sans être administrateur.
        $etaitOfficiel = (bool) $this->record?->estOfficiel();
        if ($this->officiel !== $etaitOfficiel) {
            $this->authorize('publierOfficiel', Annonce::class);
        }

        $validated['debut'] = Carbon::parse($validated['debut'], Annonce::FUSEAU)->utc();
        $validated['fin'] = Carbon::parse($validated['fin'], Annonce::FUSEAU)->utc();
        $validated['quartier_id'] = filled($validated['quartier_id'] ?? null) ? (int) $validated['quartier_id'] : null;
        $validated['consignes'] = trim((string) ($validated['consignes'] ?? '')) ?: null;

        if ($this->record) {
            $annonce = $this->record;
            $annonce->fill($validated);
        } else {
            $annonce = new Annonce($validated);
            $annonce->user()->associate(auth()->user());
        }
        $annonce->officiel = $this->officiel;
        $annonce->langage_clair_valide_le = $this->langageClairValide && $validated['langage_clair'] !== null
            ? ($dejaValide ? $annonce->langage_clair_valide_le : now())
            : null;
        $annonce->save();

        if ($this->officiel && ! $etaitOfficiel) {
            ActionLog::record('message_officiel_publie', $annonce);
        }

        // F30 : annonce importante déjà visible → habitants prévenus tout de suite (une seule fois) ;
        // programmée → la commande annonces:notify s'en charge à son début.
        $notifiee = app(NotifierAnnonce::class)->notifierSiVisible($annonce);

        Flux::toast(variant: 'success', text: ($this->record ? 'Message modifié.' : 'Message publié.')
            .($notifiee ? ' Les habitants concernés sont prévenus.' : ''));

        $this->redirectRoute('agent.annonces.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Haut Conseil de la Ville"
        :title="$record ? 'Modifier le message' : 'Publier un message ou une alerte'"
        :breadcrumb="['Messages généraux' => route('agent.annonces.index'), ($record ? 'Modifier' : 'Nouveau') => null]"
        :title="$record ? 'Modifier le message' : 'Publier un message général'"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Messages généraux' => route('agent.annonces.index'), ($record ? 'Modifier' : 'Nouveau') => null]"
    />

    @if ($record)
        <x-audit-history :subject="$record" variant="resume" />
    @endif

    @unless ($record)
        <div class="flex flex-wrap items-center gap-2 rounded-md border border-line bg-surface p-4">
            <flux:text class="text-sm font-medium">Partir d’un modèle :</flux:text>
            @foreach (\App\Models\Annonce::MODELES as $cle => $modele)
                <flux:button size="sm" icon="bolt" wire:click="appliquerModele('{{ $cle }}')">{{ $modele['libelle'] }}</flux:button>
            @endforeach
            <flux:text class="w-full text-xs">Le formulaire est pré-rempli (quartier, consignes, heure de fin estimée) : relisez puis publiez.</flux:text>
        </div>
    @endunless

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <flux:input wire:model.live.debounce.400ms="titre" label="Titre" maxlength="120" required
            placeholder="Ex. Coupure d’eau à Ambohijanahary" />

        @can('publierOfficiel', \App\Models\Annonce::class)
            <div class="rounded-md border border-line p-4">
                <flux:checkbox wire:model.live="officiel" label="Message officiel du Haut Conseil"
                    description="Affiché en tête de toutes les pages, au-dessus des autres messages, avec la signature du Haut Conseil. Réservé aux administrateurs ; la publication est journalisée." />
            </div>
        @endcan

        <flux:textarea wire:model.live.debounce.400ms="contenu" :label="$officiel ? 'Ce qu’il faut savoir' : 'Contenu'" rows="4" maxlength="2000" required
            description="Dites ce qu’il faut savoir et ce qu’il faut faire." />

        <flux:textarea wire:model.live.debounce.400ms="consignes" :label="$officiel ? 'Ce qu’il faut faire' : 'Consignes à suivre'" rows="4" maxlength="2000"
            description="Une consigne par ligne. Facultatif." placeholder="Éloignez-vous des berges et des zones basses." />

        {{-- F89 : version en langage clair, affichée aux habitants avec « Relu par la mairie » une fois validée. --}}
        <div class="space-y-3 rounded-md border border-line p-4">
            <flux:textarea wire:model="langage_clair" label="Version en langage clair" rows="4" maxlength="2000"
                description="Phrases courtes, mots de tous les jours. Gardez les heures, les montants et les numéros utiles. Facultatif : sans version validée, les habitants voient une version simplifiée automatiquement."
                placeholder="Ex. Il n’y a plus d’eau au robinet dans le quartier Nord jusqu’à 18 h. Gardez de l’eau en bouteille." />
            <flux:checkbox wire:model="langageClairValide" label="J’ai relu cette version : la publier avec le badge « Relu par la mairie »" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="niveau" label="Gravité">
                @foreach (\App\Models\Annonce::NIVEAU_LIBELLES as $code => $libelle)
                    <flux:select.option :value="$code">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="quartier_id" label="Quartier concerné">
                <flux:select.option value="">Toute la ville</flux:select.option>
                @foreach ($this->quartiers as $id => $nom)
                    <flux:select.option :value="$id">{{ $nom }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="debut" label="Début de diffusion" type="datetime-local" required />
            <flux:input wire:model="fin" label="Fin de diffusion" type="datetime-local" required />
        </div>
        <flux:text class="-mt-3 text-xs">Heure de Madagascar.</flux:text>

        <div class="space-y-2">
            <flux:text class="text-sm font-medium">
                Aperçu du bandeau{{ $quartier_id !== '' ? ' (tel que le voient les habitants du quartier)' : '' }}
            </flux:text>
            <div class="overflow-hidden rounded-md border border-line">
                <x-tn.bandeau-annonce
                    :variante="$quartier_id !== '' ? 'renforce' : 'standard'"
                    :officiel="$officiel"
                    :date="rescue(fn () => \Illuminate\Support\Carbon::parse($debut, \App\Models\Annonce::FUSEAU), null, false)"
                    :quartier="$this->quartiers[$quartier_id] ?? null"
                    :consignes="(new \App\Models\Annonce(['consignes' => $consignes]))->listeConsignes()"
                    :niveau="$niveau"
                    :titre="$titre !== '' ? $titre : 'Titre du message'"
                    :contenu="$contenu !== '' ? $contenu : 'Le contenu du message apparaîtra ici.'"
                />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ $record ? 'Enregistrer' : 'Publier' }}</flux:button>
            <flux:button :href="route('agent.annonces.index')" wire:navigate variant="ghost">Annuler</flux:button>
            <div wire:loading wire:target="save"><flux:text>Enregistrement…</flux:text></div>
        </div>
    </form>

    @if ($record)
        <x-audit-history :subject="$record" />
    @endif
</section>
