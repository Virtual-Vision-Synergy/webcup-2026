{{--
    Gabarit des pages publiques (générées par `make:feature --public`).
    Connecté : le gabarit habituel avec menu latéral. Invité : le gabarit du site Terra Nova.
--}}
@auth
    <x-layouts::app :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app>
@else
    <x-layouts::site :title="$title ?? null">
        {{ $slot }}
    </x-layouts::site>
@endauth
