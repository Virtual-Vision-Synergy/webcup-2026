@props(['horaires' => false])

{{-- Saisie des traductions (propriété Livewire `traductions`). Champs vides = repli sur le français. --}}
<fieldset class="space-y-4 rounded-md border border-line p-4">
    <legend class="px-2 font-mono text-xs uppercase tracking-[.06em] text-ink-2">Traductions (facultatif)</legend>
    <p class="text-sm text-ink-2">Si un champ reste vide, le texte français est affiché.</p>

    @foreach (\App\Models\Traduction::LANGUES_TRADUITES as $code => $libelle)
        <div class="space-y-3" wire:key="traduction-{{ $code }}">
            <h3 class="font-semibold text-ink">{{ $libelle }}</h3>
            <flux:input wire:model="traductions.{{ $code }}.titre" label="Titre ({{ $libelle }})" />
            <flux:textarea wire:model="traductions.{{ $code }}.description" label="Description ({{ $libelle }})" rows="3" />
            @if ($horaires)
                <flux:textarea wire:model="traductions.{{ $code }}.horaires" label="Horaires ({{ $libelle }})" rows="2" />
            @endif
        </div>
    @endforeach
</fieldset>
