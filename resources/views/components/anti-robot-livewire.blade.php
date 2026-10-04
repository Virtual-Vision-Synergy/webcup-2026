{{-- F81 : champ piège invisible des formulaires Livewire (trait App\Concerns\ProtegeContreRobots). --}}
<div aria-hidden="true" class="pointer-events-none absolute -left-[9999px] top-auto h-px w-px overflow-hidden">
    <label for="site_web_formulaire">Ne pas remplir ce champ</label>
    <input type="text" id="site_web_formulaire" wire:model="site_web" tabindex="-1" autocomplete="off">
</div>
