@props(['formulaire'])

{{-- F81 : champ piège invisible (hors écran, ignoré par le clavier et les lecteurs d'écran) + jeton d'horodatage. --}}
<div aria-hidden="true" class="pointer-events-none absolute -left-[9999px] top-auto h-px w-px overflow-hidden">
    <label for="{{ \App\Services\ProtectionFormulaires::CHAMP_PIEGE }}-{{ $formulaire }}">Ne pas remplir ce champ</label>
    <input type="text" id="{{ \App\Services\ProtectionFormulaires::CHAMP_PIEGE }}-{{ $formulaire }}" name="{{ \App\Services\ProtectionFormulaires::CHAMP_PIEGE }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Services\ProtectionFormulaires::CHAMP_JETON }}" value="{{ app(\App\Services\ProtectionFormulaires::class)->jeton($formulaire) }}">
