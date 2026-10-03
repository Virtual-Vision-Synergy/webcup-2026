{{-- F59 : bandeau « Chargement… » affiché pendant une action Livewire qui dure (connexion lente). Voir .tn-chargement dans app.css. --}}
<div class="tn-chargement" aria-hidden="true">
    <span class="mt-2 inline-flex items-center gap-2 rounded-sm border border-line bg-surface px-3 py-1.5 text-sm font-medium text-ink shadow-sm">
        <flux:icon.loading class="size-4 text-cyan" />
        {{ __('Chargement… merci de patienter') }}
    </span>
</div>
