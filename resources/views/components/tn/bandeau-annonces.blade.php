{{--
    Messages généraux (D18) et alertes ciblées (F29) en cours de diffusion, inclus dans tous les gabarits : visibles par tous, invités compris.
    Les alertes du quartier de l'habitant passent en premier, en version renforcée ; celles des autres quartiers restent visibles en version compacte.
--}}
@php
    $habitant = auth()->user();
    $annoncesEnDiffusion = \App\Models\Annonce::enDiffusion($habitant);
@endphp

@if ($annoncesEnDiffusion->isNotEmpty())
    <div {{ $attributes->class('tn-bandeaux-annonces') }}>
        @foreach ($annoncesEnDiffusion as $annonce)
            @php
                $sansQuartier = $habitant !== null && $habitant->quartier_id === null;
                $variante = match (true) {
                    $annonce->concerne($habitant) => 'renforce',
                    $annonce->estCiblee() && ! $sansQuartier => 'compact',
                    default => 'standard',
                };
            @endphp
            <x-tn.bandeau-annonce
                :variante="$variante"
                :niveau="$annonce->niveau"
                :titre="$annonce->titre"
                :contenu="$annonce->contenu"
                :consignes="$annonce->listeConsignes()"
                :quartier="$annonce->nomQuartier()"
                :lien="$annonce->estCiblee() || $annonce->listeConsignes() !== [] ? route('alertes.show', $annonce->id) : null"
                :inviter-quartier="$sansQuartier && $annonce->estCiblee()"
                :cle="$annonce->cleFermeture()"
            />
        @endforeach
    </div>
@endif
