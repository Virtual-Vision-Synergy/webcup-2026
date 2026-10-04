<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Concerns\HasConfidentialFields;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
| F70 : donnée confidentielle masquée par défaut (« •••••• (confidentiel) »).
| La valeur n'est lue qu'APRÈS « Afficher » : policy viewConfidential (même règle de service, admin compris),
| motif obligatoire, consultation journalisée dans F47 (qui, quand, élément, champ, motif — jamais la valeur).
| Elle n'est gardée que pour cette page : rien en session, un rechargement la masque à nouveau.
| Bonus : pendant 5 minutes, le même dossier se ré-affiche sans redemander le motif (la session ne garde qu'une heure).
|
| Utilisation : <livewire:donnee-confidentielle :subject="$demarche" champ="telephone_demandeur" />
*/
new class extends Component {
    use ThrottlesPerUser;

    /** Motifs courants (rapides à choisir en usage normal) ; « Autre » demande une précision. */
    public const MOTIFS = [
        'traitement' => 'Traitement de la demande',
        'contact' => 'Contact du citoyen',
        'verification' => 'Vérification de pièce',
        'autre' => 'Autre',
    ];

    /** Durée pendant laquelle le motif n'est pas redemandé pour le même dossier. */
    public const FENETRE_MINUTES = 5;

    #[Locked]
    public Model $subject;

    #[Locked]
    public string $champ;

    #[Locked]
    public string $label = '';

    #[Locked]
    public ?string $valeur = null;

    #[Locked]
    public bool $revele = false;

    public string $motif = '';

    public string $motifAutre = '';

    public function mount(Model $subject, string $champ): void
    {
        abort_unless(in_array(HasConfidentialFields::class, class_uses_recursive($subject), true), 404);

        /** @var Model&HasConfidentialFields $subject */
        abort_unless($subject->hasConfidentialField($champ), 404);

        $this->subject = $subject;
        $this->champ = $champ;
        $this->label = $subject->confidentialLabel($champ);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'motif' => ['required', Rule::in(array_keys(self::MOTIFS))],
            'motifAutre' => ['nullable', 'required_if:motif,autre', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['motif' => 'motif de consultation', 'motifAutre' => 'précision du motif'];
    }

    public function reveler(): void
    {
        AuditLogger::autoriser('viewConfidential', $this->subject);

        $dansLaFenetre = $this->dansLaFenetre();

        if (! $dansLaFenetre) {
            $this->validate();
        }

        $this->throttlePerUser('donnee-confidentielle', 30, 60);

        $motif = match (true) {
            $dansLaFenetre => 'Même consultation (moins de '.self::FENETRE_MINUTES.' min)',
            $this->motif === 'autre' => 'Autre : '.trim($this->motifAutre),
            default => self::MOTIFS[$this->motif],
        };

        AuditLogger::log('confidential_viewed', $this->subject, [
            'champ' => ['avant' => null, 'apres' => $this->label],
            'motif' => ['avant' => null, 'apres' => $motif],
        ]);

        session()->put($this->cleFenetre(), now()->timestamp);

        /** @var Model&HasConfidentialFields $subject */
        $subject = $this->subject;
        $this->valeur = $subject->confidentialValue($this->champ) ?? 'Non renseigné';
        $this->revele = true;
        $this->reset('motif', 'motifAutre');
        unset($this->dansLaFenetre);

        Flux::modal($this->nomModale())->close();
    }

    public function masquer(): void
    {
        $this->valeur = null;
        $this->revele = false;
    }

    /**
     * Motif déjà donné pour ce dossier il y a moins de 5 minutes.
     */
    #[Computed]
    public function dansLaFenetre(): bool
    {
        $horodatage = session()->get($this->cleFenetre());

        return is_int($horodatage) && now()->timestamp - $horodatage < self::FENETRE_MINUTES * 60;
    }

    public function nomModale(): string
    {
        return 'confidentiel-'.$this->getId();
    }

    private function cleFenetre(): string
    {
        return 'confidentiel.'.class_basename($this->subject).'.'.$this->subject->getKey();
    }
}; ?>

<div class="inline-flex flex-wrap items-center gap-2" data-test="confidentiel-{{ $champ }}">
    @if ($revele)
        <span class="font-medium text-ink">{{ $valeur }}</span>
        <flux:button size="xs" variant="ghost" icon="eye-slash" wire:click="masquer">Masquer</flux:button>
    @else
        <span class="inline-flex items-center gap-1 text-ink-2">
            <flux:icon.lock-closed variant="micro" class="size-3.5" aria-hidden="true" />
            <span aria-hidden="true">••••••</span>
            <span class="text-xs">(confidentiel)</span>
        </span>

        @if ($this->dansLaFenetre)
            <flux:button size="xs" icon="eye" wire:click="reveler" aria-label="Afficher : {{ $label }}">Afficher</flux:button>
        @else
            <flux:modal.trigger :name="$this->nomModale()">
                <flux:button size="xs" icon="eye" aria-label="Afficher : {{ $label }}">Afficher</flux:button>
            </flux:modal.trigger>

            <flux:modal :name="$this->nomModale()" class="w-full md:w-96">
                <form wire:submit="reveler" class="space-y-4 text-start">
                    <div>
                        <flux:heading size="lg">Afficher une donnée confidentielle</flux:heading>
                        <flux:text class="mt-1">{{ $label }} — indiquez pourquoi vous la consultez. Cette consultation est enregistrée dans le journal.</flux:text>
                    </div>

                    <flux:select wire:model.live="motif" label="Motif de consultation" required>
                        <flux:select.option value="">Choisir un motif…</flux:select.option>
                        @foreach ($this::MOTIFS as $cle => $libelle)
                            <flux:select.option :value="$cle">{{ $libelle }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($motif === 'autre')
                        <flux:input wire:model="motifAutre" label="Précisez le motif" maxlength="200" required />
                    @endif

                    @error('throttle')
                        <flux:text class="text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">Annuler</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary" icon="eye">
                            <span wire:loading.remove wire:target="reveler">Afficher</span>
                            <span wire:loading wire:target="reveler">Vérification…</span>
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    @endif
</div>
