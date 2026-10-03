{{--
    F30 : cloche des notifications dans le header de l'espace connecté (mobile et desktop).
    Badge texte (masqué à 0, « 9+ » au-delà de 9) et aria-label : l'information ne repose pas sur la couleur.
    Rafraîchi au chargement de page, puis toutes les 60 s par un petit appel JSON (notifications.count).
--}}
@php
    $nonLues = auth()->user()->unreadNotifications()->count();
    $libelle = match (true) {
        $nonLues === 0 => 'Aucune notification non lue',
        $nonLues === 1 => '1 notification non lue',
        default => $nonLues.' notifications non lues',
    };
@endphp

<a
    href="{{ route('notifications.index') }}"
    wire:navigate
    data-test="cloche-header"
    data-compteur-url="{{ route('notifications.count') }}"
    aria-label="{{ $libelle }}"
    {{ $attributes->class('relative flex size-11 items-center justify-center rounded-full text-ink-2 hover:text-ink') }}
    x-data
    x-init="
        const lien = $el;
        const maj = () => fetch(lien.dataset.compteurUrl, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((d) => {
                if (! d) return;
                const n = Number(d.non_lues) || 0;
                const badge = lien.querySelector('[data-badge]');
                badge.textContent = n > 9 ? '9+' : String(n);
                badge.hidden = n === 0;
                lien.setAttribute('aria-label', n === 0 ? 'Aucune notification non lue' : n + (n > 1 ? ' notifications non lues' : ' notification non lue'));
            })
            .catch(() => {});
        const minuteur = setInterval(() => document.visibilityState === 'visible' && maj(), 60000);
        document.addEventListener('livewire:navigating', () => clearInterval(minuteur), { once: true });
    "
>
    <flux:icon name="bell" class="size-6" aria-hidden="true" />
    <span
        data-badge
        data-test="cloche-header-compteur"
        aria-hidden="true"
        @if ($nonLues === 0) hidden @endif
        class="absolute -end-0.5 -top-0.5 min-w-5 rounded-full border-2 border-night bg-magenta px-1 text-center text-[0.6875rem] font-bold leading-4 text-white"
    >{{ $nonLues > 9 ? '9+' : $nonLues }}</span>
</a>
