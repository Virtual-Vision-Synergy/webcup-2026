{{-- F42 : légende des champs obligatoires, à placer en tête de formulaire. --}}
<p {{ $attributes->class('text-sm text-ink-2') }}>
    {{ __('Les champs marqués d’un astérisque') }} (<span class="text-magenta" aria-hidden="true">*</span><span class="sr-only">{{ __('astérisque') }}</span>) {{ __('sont obligatoires.') }}
</p>
