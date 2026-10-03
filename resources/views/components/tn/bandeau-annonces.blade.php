{{-- Messages généraux en cours de diffusion (D18), inclus dans tous les gabarits : visibles par tous, invités compris. --}}
@php
    $annoncesEnDiffusion = \App\Models\Annonce::enDiffusion();
@endphp

@if ($annoncesEnDiffusion->isNotEmpty())
    <div {{ $attributes->class('tn-bandeaux-annonces') }}>
        @foreach ($annoncesEnDiffusion as $annonce)
            <x-tn.bandeau-annonce
                :niveau="$annonce->niveau"
                :titre="$annonce->titre"
                :contenu="$annonce->contenu"
                :cle="$annonce->cleFermeture()"
            />
        @endforeach
    </div>
@endif
