<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-onboarding.retour />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
