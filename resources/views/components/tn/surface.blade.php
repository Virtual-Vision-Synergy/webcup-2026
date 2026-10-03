@props(['padding' => 'p-5 md:p-6'])

{{-- Surface pleine (pas de flou) : le contenu courant repose sur ces blocs. --}}
<div {{ $attributes->class(['rounded-md border border-line bg-surface', $padding]) }}>
    {{ $slot }}
</div>
