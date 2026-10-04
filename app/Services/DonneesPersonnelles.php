<?php

namespace App\Services;

use App\Models\AvisProjet;
use App\Models\Demarche;
use App\Models\KnownDevice;
use App\Models\LoginAttempt;
use App\Models\Message;
use App\Models\Remontee;
use App\Models\RendezVous;
use App\Models\Signalement;
use App\Models\Soutien;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * F55 : rassemble les données personnelles qu'une personne a sur la plateforme, avec un résumé lisible.
 * Toujours construit pour UN utilisateur précis (le connecté, vérifié par UserPolicy::exportPersonalData) :
 * chaque requête est filtrée par user_id, jamais par un identifiant venant du navigateur.
 */
class DonneesPersonnelles
{
    /** Nombre de connexions récentes reprises dans l'export. */
    public const CONNEXIONS_RECENTES = 10;

    public function __construct(private readonly User $user) {}

    public static function pour(User $user): self
    {
        return new self($user);
    }

    /**
     * Toutes les données, prêtes pour la page imprimable et l'export JSON.
     *
     * @return array<string, mixed>
     */
    public function tout(): array
    {
        $user = $this->user->loadMissing(['role', 'quartierResidence']);

        $demarches = Demarche::query()->where('user_id', $user->id)->with('service')->latest()->get();
        $signalements = Signalement::query()->where('user_id', $user->id)->withCount('soutiens')->latest()->get();
        $soutiens = Soutien::query()->where('user_id', $user->id)->with('signalement')->latest()->get();
        $remontees = Remontee::query()->where('user_id', $user->id)->latest()->get();
        $rendezVous = RendezVous::query()->where('user_id', $user->id)->with(['service', 'creneau'])->latest()->get();
        $avis = AvisProjet::query()->where('user_id', $user->id)->with('projet')->latest()->get();
        $messages = Message::query()->where('user_id', $user->id)->latest()->get();
        $connexions = LoginAttempt::query()->where('user_id', $user->id)->latest()->latest('id')->limit(self::CONNEXIONS_RECENTES)->get();
        $appareils = KnownDevice::query()->where('user_id', $user->id)->latest('last_seen_at')->get();

        $donnees = [
            'genere_le' => now()->toIso8601String(),
            'profil' => [
                'nom' => $user->name,
                'email' => $user->emailAffichable(),
                'identifiant' => $user->identifiant,
                'telephone' => $user->telephone,
                'quartier' => $user->quartierResidence->nom ?? $user->quartier,
                'role' => $user->role->label,
                'langue' => $user->langue,
                'alertes_par_email' => $user->notifier_par_email,
                'double_authentification' => $user->two_factor_confirmed_at !== null,
                'email_verifie' => $user->email_verified_at !== null,
                'compte_cree_le' => $this->date($user->created_at),
            ],
            'demarches' => [
                'par_etat' => $this->compterParEtat($demarches->countBy('statut')->all(), Demarche::STATUT_OPTIONS, Demarche::libelleStatut(...)),
                'liste' => $demarches->map(fn (Demarche $d): array => [
                    'reference' => '#'.$d->id,
                    'titre' => $d->titre,
                    'service' => $d->service?->nom,
                    'etat' => Demarche::libelleStatut($d->statut),
                    'deposee_le' => $this->date($d->created_at),
                    'mise_a_jour_le' => $this->date($d->updated_at),
                ])->all(),
            ],
            'signalements' => [
                'par_etat' => $this->compterParEtat($signalements->countBy('statut')->all(), Signalement::STATUT_OPTIONS, Signalement::libelleStatut(...)),
                'liste' => $signalements->map(fn (Signalement $s): array => [
                    'reference' => '#'.$s->id,
                    'categorie' => Signalement::libelleCategorie($s->categorie),
                    'lieu' => $s->lieu,
                    'description' => $s->description,
                    'etat' => Signalement::libelleStatut($s->statut),
                    'soutiens_recus' => (int) $s->getAttribute('soutiens_count'),
                    'signale_le' => $this->date($s->created_at),
                ])->all(),
            ],
            'soutiens' => $soutiens->map(fn (Soutien $s): array => [
                'signalement' => '#'.$s->signalement->id.' · '.Signalement::libelleCategorie($s->signalement->categorie),
                'lieu' => $s->signalement->lieu,
                'etat' => Signalement::libelleStatut($s->signalement->statut),
                'soutenu_le' => $this->date($s->created_at),
            ])->all(),
            'remontees' => [
                'par_etat' => $this->compterParEtat($remontees->countBy('statut')->all(), Remontee::STATUT_OPTIONS, Remontee::libelleStatut(...)),
                'liste' => $remontees->map(fn (Remontee $r): array => [
                    'reference' => $r->reference,
                    'categorie' => Remontee::libelleCategorie($r->categorie),
                    'objet' => $r->objet,
                    'etat' => Remontee::libelleStatut($r->statut),
                    'envoyee_le' => $this->date($r->envoyee_le ?? $r->created_at),
                    'reponse' => $r->reponse,
                ])->all(),
            ],
            'rendez_vous' => $rendezVous->map(fn (RendezVous $r): array => [
                'service' => $r->service->nom,
                'date' => $this->date($r->creneau->debut),
                'motif' => $r->motif,
                'etat' => RendezVous::libelleStatut($r->statut),
            ])->all(),
            'avis_projets' => $avis->map(fn (AvisProjet $a): array => [
                'projet' => $a->projet->titre,
                'position' => $a->positionLabel(),
                'commentaire' => $a->commentaire,
                'donne_le' => $this->date($a->created_at),
            ])->all(),
            'messages' => $messages->map(fn (Message $m): array => [
                'sujet' => $m->sujet,
                'envoye_le' => $this->date($m->created_at),
            ])->all(),
            'connexions_recentes' => $connexions->map(fn (LoginAttempt $c): array => [
                'date' => $this->date($c->created_at),
                'resultat' => $c->successful ? 'Réussie' : $c->reasonLabel(),
                'adresse_ip' => $c->ip,
                'navigateur' => $c->user_agent,
            ])->all(),
            'appareils' => $appareils->map(fn (KnownDevice $a): array => [
                'appareil' => $a->libelleAppareil(),
                'adresse_approx' => $a->ipAffichee(),
                'premiere_connexion' => $this->date($a->first_seen_at),
                'derniere_connexion' => $this->date($a->last_seen_at),
                'revoque' => $a->isRevoked(),
            ])->all(),
        ];

        $donnees['resume'] = $this->resume($donnees);

        return $donnees;
    }

    /**
     * Phrases de synthèse : ce que la personne doit retenir sans lire le détail.
     *
     * @param  array<string, mixed>  $donnees
     * @return list<string>
     */
    public function resume(array $donnees): array
    {
        $phrases = [];

        $nbDemarches = count($donnees['demarches']['liste']);
        $enAttente = ($donnees['demarches']['par_etat'][Demarche::libelleStatut('deposee')] ?? 0)
            + ($donnees['demarches']['par_etat'][Demarche::libelleStatut('en_cours')] ?? 0);
        $phrases[] = $nbDemarches === 0
            ? 'Vous n’avez déposé aucune démarche.'
            : "Vous avez déposé {$nbDemarches} démarche(s), dont {$enAttente} encore en attente de réponse.";

        $nbSignalements = count($donnees['signalements']['liste']);
        $ouverts = 0;
        foreach (Signalement::STATUTS_OUVERTS as $statut) {
            $ouverts += $donnees['signalements']['par_etat'][Signalement::libelleStatut($statut)] ?? 0;
        }
        $phrases[] = $nbSignalements === 0
            ? 'Vous n’avez fait aucun signalement.'
            : "Vous avez fait {$nbSignalements} signalement(s), dont {$ouverts} encore ouvert(s).";

        $phrases[] = count($donnees['soutiens']) === 0
            ? 'Vous n’avez soutenu aucun signalement d’un autre habitant.'
            : 'Vous avez soutenu '.count($donnees['soutiens']).' signalement(s) d’autres habitants.';

        $nbRemontees = count($donnees['remontees']['liste']);
        if ($nbRemontees > 0) {
            $phrases[] = "Vous avez fait {$nbRemontees} remontée(s) sur vos données.";
        }

        $echecs = count(array_filter($donnees['connexions_recentes'], fn (array $connexion): bool => $connexion['resultat'] !== 'Réussie'));
        $phrases[] = $echecs > 0
            ? "Attention : {$echecs} tentative(s) de connexion échouée(s) parmi les dernières. Si ce n’était pas vous, changez votre mot de passe."
            : 'Aucune tentative de connexion échouée récente sur votre compte.';

        if (! $donnees['profil']['double_authentification']) {
            $phrases[] = 'Conseil : activez la double authentification pour mieux protéger votre compte.';
        }

        return $phrases;
    }

    /**
     * Nombre d'éléments pour chaque état connu (même à zéro), indexé par libellé lisible.
     *
     * @param  array<array-key, int>  $compte
     * @param  array<int, string>  $options
     * @param  callable(string): string  $libelle
     * @return array<string, int>
     */
    private function compterParEtat(array $compte, array $options, callable $libelle): array
    {
        $resultat = [];

        foreach ($options as $statut) {
            $resultat[$libelle($statut)] = (int) ($compte[$statut] ?? 0);
        }

        return $resultat;
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->timezone(KnownDevice::FUSEAU)->format('d/m/Y H:i');
    }
}
