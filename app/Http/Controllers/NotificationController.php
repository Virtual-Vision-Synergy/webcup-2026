<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\Demarche;
use App\Models\RendezVous;
use App\Models\Signalement;
use App\Notifications\StatutDemandeChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

/**
 * F30 : actions sur les notifications de l'utilisateur connecté (la liste est la page Livewire notifications.index).
 * Chaque action vérifie la Policy : la notification d'un autre utilisateur donne 403.
 * Seul read_at est modifié, côté serveur ; aucune donnée de la requête n'est enregistrée.
 */
class NotificationController extends Controller
{
    public function lire(DatabaseNotification $notification): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $notification->markAsRead();

        return back(fallback: route('notifications.index'))->with('status', 'Notification marquée comme lue.');
    }

    public function toutLire(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', DatabaseNotification::class);

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back(fallback: route('notifications.index'))->with('status', 'Toutes vos notifications sont marquées comme lues.');
    }

    /**
     * Ouvre la notification : la marque comme lue puis mène à l'annonce (ou à la liste si elle n'est plus en ligne),
     * à la demande dont l'état a changé (F49), ou à la fiche du rendez-vous pour un rappel (F40).
     */
    public function ouvrir(DatabaseNotification $notification): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $notification->markAsRead();

        if ($notification->type === StatutDemandeChange::class) {
            return $this->ouvrirDemande($notification);
        }

        // F40 : rappel de rendez-vous → sa fiche (RendezVousPolicy::view y est vérifiée).
        $rendezVousId = $notification->data['rendez_vous_id'] ?? null;
        if (is_int($rendezVousId)) {
            return RendezVous::query()->whereKey($rendezVousId)->exists()
                ? redirect()->route('appointments.show', $rendezVousId)
                : redirect()->route('notifications.index')->with('status', 'Ce rendez-vous n’existe plus.');
        }

        $annonceId = $notification->data['annonce_id'] ?? null;
        $annonce = is_int($annonceId) ? Annonce::query()->find($annonceId) : null;

        if ($annonce === null || $annonce->statut() !== 'en_cours') {
            return redirect()->route('notifications.index')->with('status', 'Cette annonce n’est plus en ligne.');
        }

        return redirect()->route('alertes.show', $annonce);
    }

    /**
     * F49 : l'URL est reconstruite depuis le type (liste blanche) et l'identifiant, jamais lue dans data['url']
     * (pas de redirection ouverte). La page de la demande garde sa propre Policy.
     */
    private function ouvrirDemande(DatabaseNotification $notification): RedirectResponse
    {
        $data = (array) $notification->data;

        if (! StatutDemandeChange::estAvisDeStatut($data)) {
            return redirect()->route('notifications.index')->with('status', __('Cette demande n’est plus disponible.'));
        }

        $existe = $data['demande_type'] === StatutDemandeChange::TYPE_DEMARCHE
            ? Demarche::query()->whereKey((int) $data['demande_id'])->exists()
            : Signalement::query()->whereKey((int) $data['demande_id'])->exists();

        if (! $existe) {
            return redirect()->route('notifications.index')->with('status', __('Cette demande n’est plus disponible.'));
        }

        return redirect()->to((string) StatutDemandeChange::urlDepuis($data));
    }

    /**
     * Compteur de non lues pour le rafraîchissement léger de la cloche.
     */
    public function compteur(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DatabaseNotification::class);

        return response()->json(['non_lues' => $request->user()->unreadNotifications()->count()]);
    }
}
