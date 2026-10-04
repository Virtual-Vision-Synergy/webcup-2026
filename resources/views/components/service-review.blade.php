@props([
    'review',
    'author' => null,
])

{{-- F76 : un avis publié (fiche du service, page « Tous les avis »). Auteur : prénom + initiale, jamais l'e-mail. --}}
<article {{ $attributes->class('rounded-md border border-line p-4') }}>
    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-0.5 text-amber" aria-hidden="true">
            @for ($i = 1; $i <= 5; $i++)
                <flux:icon.star variant="{{ $i <= $review->rating ? 'solid' : 'outline' }}" class="size-4" />
            @endfor
        </span>
        <span class="text-sm font-semibold text-ink">{{ $review->libelleNote() }}</span>
        @if ($review->verified_usage)
            <x-tn.status-badge etat="normal" icon="check-badge">{{ __('A utilisé ce service') }}</x-tn.status-badge>
        @endif
    </div>
    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $review->comment }}</p>
    <p class="mt-2 font-mono text-xs text-ink-2">
        {{ $author ?? $review->auteurPublic() }} · {{ \App\Models\ServiceReview::dateLongue($review->updated_at) }}
    </p>
    @if ($review->response)
        <div class="mt-3 border-s-2 border-cyan ps-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-cyan">{{ __('Réponse du service') }}</p>
            <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $review->response }}</p>
            <p class="mt-1 font-mono text-xs text-ink-2">{{ \App\Models\ServiceReview::dateLongue($review->responded_at) }}</p>
        </div>
    @endif
</article>
