<?php

namespace App\Http\Controllers;

use App\Models\Signalement;
use App\Services\OptimiseurImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * F69 (A2) : la photo d'un signalement n'est jamais servie par /storage. Elle est déchiffrée et envoyée
 * uniquement à ceux que la Policy autorise à voir le signalement (son auteur, les agents, les admins).
 */
class SignalementPhotoController extends Controller
{
    public function show(Request $request, Signalement $signalement): Response
    {
        Gate::authorize('view', $signalement);

        $chemin = $signalement->photo;
        abort_if(blank($chemin), 404);

        if ($request->boolean('miniature') && OptimiseurImage::miniature($chemin) !== null) {
            $chemin = (string) OptimiseurImage::miniature($chemin);
        }

        $contenu = OptimiseurImage::lire($chemin);
        abort_if($contenu === null, 404);

        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenu) ?: 'application/octet-stream';
        abort_unless(str_starts_with($type, 'image/'), 404);

        return response($contenu, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
