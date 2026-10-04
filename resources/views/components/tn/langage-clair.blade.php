{{--
    F89 : bascule « Version simple / Version complète » d'un contenu administratif (service, message, démarche).
    - $version vient de App\Support\LangageClair (texte, relu, essentiels).
    - Le slot est la version complète ; la version simple reprend toujours les éléments importants (délais, pièces, montants, contacts).
    - Le choix est mémorisé dans le navigateur ($persist) : il suit l'habitant de page en page.
--}}
@props([
    'version',
    'titre' => null,
])

<div {{ $attributes->class('space-y-4') }} x-data="{ simple: $persist(false).as('tn-langage-clair') }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        @if ($titre)
            <x-tn.section-label as="h2">{{ $titre }}</x-tn.section-label>
        @endif
        <div class="inline-flex rounded-md border border-line p-0.5 text-sm" role="group" aria-label="{{ __('Choisir la version du texte') }}">
            <button type="button" class="min-h-9 cursor-pointer rounded px-3 font-medium" x-on:click="simple = true"
                x-bind:aria-pressed="simple ? 'true' : 'false'" x-bind:class="simple ? 'bg-ink text-surface' : 'text-ink-2 hover:text-ink'">
                {{ __('Version simple') }}
            </button>
            <button type="button" class="min-h-9 cursor-pointer rounded px-3 font-medium" x-on:click="simple = false"
                x-bind:aria-pressed="simple ? 'false' : 'true'" x-bind:class="simple ? 'text-ink-2 hover:text-ink' : 'bg-ink text-surface'">
                {{ __('Version complète') }}
            </button>
        </div>
    </div>

    <div x-show="! simple" data-version="complete">
        {{ $slot }}
    </div>

    <div x-show="simple" x-cloak data-version="simple" class="space-y-4">
        @if ($version['relu'])
            <x-tn.status-badge etat="normal" icon="check-badge">{{ __('Relu par la mairie') }}</x-tn.status-badge>
        @else
            <x-tn.status-badge etat="info">{{ __('Simplifié automatiquement, non relu') }}</x-tn.status-badge>
        @endif

        @if (filled($version['texte']))
            <p class="whitespace-pre-line text-lg leading-relaxed text-ink">{{ $version['texte'] }}</p>
        @endif

        @if ($version['essentiels'] !== [])
            <dl class="space-y-3 rounded-md border border-line bg-surface p-4">
                <p class="text-sm font-semibold text-ink">{{ __('À retenir') }}</p>
                @foreach ($version['essentiels'] as $groupe)
                    <div>
                        <dt class="text-sm text-ink-2">{{ __($groupe['label']) }}</dt>
                        @foreach ($groupe['valeurs'] as $valeur)
                            <dd class="font-medium text-ink">{{ $valeur }}</dd>
                        @endforeach
                    </div>
                @endforeach
            </dl>
        @endif

        @if (! $version['relu'])
            <p class="text-sm text-ink-2">{{ __('En cas de doute, la version complète fait foi.') }}</p>
        @endif
    </div>
</div>
