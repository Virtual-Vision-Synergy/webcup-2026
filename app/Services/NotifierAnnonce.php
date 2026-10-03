<?php

namespace App\Services;

use App\Jobs\EnvoyerAnnonceImportante;
use App\Models\Annonce;
use Illuminate\Support\Facades\Cache;

/**
 * F30 : prévient les habitants quand une annonce importante devient VISIBLE (pas au moment de sa saisie).
 *
 * - À l'enregistrement d'une annonce déjà en cours : notification immédiate.
 * - Annonce programmée : la commande planifiée annonces:notify l'envoie à son début.
 * - notified_at est réservé de façon atomique : jamais deux envois, même si la commande tourne deux fois.
 */
class NotifierAnnonce
{
    public const VERROU = 'annonces.notifier.verrou';

    public function estImportante(Annonce $annonce): bool
    {
        return in_array($annonce->niveau, (array) config('annonces.niveaux_notifies', []), true);
    }

    /**
     * Notifie les habitants si l'annonce est importante, en cours de diffusion et pas encore notifiée.
     */
    public function notifierSiVisible(Annonce $annonce): bool
    {
        if ($annonce->notified_at !== null || ! $this->estImportante($annonce) || $annonce->statut() !== 'en_cours') {
            return false;
        }

        $reservee = Annonce::query()
            ->whereKey($annonce->id)
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($reservee !== 1) {
            return false;
        }

        $annonce->refresh();
        EnvoyerAnnonceImportante::dispatch($annonce->id);

        return true;
    }

    /**
     * Annonces importantes arrivées à leur début et pas encore notifiées.
     *
     * @return int nombre d'annonces notifiées
     */
    public function traiterEchues(): int
    {
        return Annonce::query()
            ->active()
            ->whereNull('notified_at')
            ->whereIn('niveau', (array) config('annonces.niveaux_notifies', []))
            ->get()
            ->filter(fn (Annonce $annonce): bool => $this->notifierSiVisible($annonce))
            ->count();
    }

    /**
     * Filet de sécurité si le cron du planificateur ne tourne pas : au plus une vérification par minute.
     */
    public function traiterEchuesAuPlusUneFoisParMinute(): void
    {
        if (Cache::add(self::VERROU, true, 60)) {
            $this->traiterEchues();
        }
    }
}
