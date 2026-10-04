@props(['target' => 'save'])

{{-- F82 : bouton d'envoi désactivé pendant l'envoi, libellé « Envoi en cours… » (évite le double clic). --}}
<flux:button type="submit" wire:loading.attr="disabled" wire:target="{{ $target }}" {{ $attributes }}>
    <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
    <span wire:loading wire:target="{{ $target }}">{{ __('Envoi en cours…') }}</span>
</flux:button>
