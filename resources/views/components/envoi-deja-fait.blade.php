@props(['le' => null, 'url' => null, 'message' => 'Cette demande a déjà été envoyée', 'lien' => 'voir ma demande'])

{{-- F82 : second envoi identique refusé (trait App\Concerns\EmpecheEnvoiEnDouble). --}}
@if ($le)
    <div role="status" aria-live="polite" {{ $attributes->class('flex items-start gap-3 rounded-md border border-amber/40 bg-amber/8 p-4 text-ink') }}>
        <flux:icon.information-circle class="mt-0.5 size-5 shrink-0 text-amber" aria-hidden="true" />
        <p>
            {{ $message }} le {{ $le }}@if ($url) — <a href="{{ $url }}" wire:navigate class="font-medium text-cyan underline underline-offset-2">{{ $lien }}</a>@endif.
            <span class="block text-sm text-ink-2">Rien n’a été envoyé une seconde fois.</span>
        </p>
    </div>
@endif
