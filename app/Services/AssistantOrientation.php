<?php

namespace App\Services;

use App\Models\ConversationAssistant;
use App\Models\Demarche;
use App\Models\RegleAssistant;
use App\Models\Service;

/**
 * F91 : assistant d'orientation des habitants, sans IA.
 *
 * Ordre de lecture d'un message : urgence médicale (F86) → règles de l'admin (questions fréquentes) → annuaire des services
 * via OrientationServices (D10 : fautes, synonymes, « vouliez-vous dire »). Une reformulation IA pourra être branchée
 * derrière ReformulateurRequete ; si elle échoue, D10 continue avec les mots-clés (repli).
 *
 * Chaque réponse se termine par au moins une action (service, démarche, page utile ou « Contacter la mairie ») : jamais d'impasse.
 * Rien n'est inventé : textes et liens viennent uniquement des services et des règles enregistrés.
 *
 * @phpstan-type Action array{libelle: string, url: string, icone: string}
 * @phpstan-type Choix array{id: int, nom: string}
 * @phpstan-type Reponse array{type: string, texte: string, actions: list<Action>, choix: list<Choix>, service_id: int|null}
 */
class AssistantOrientation
{
    /** Score minimal pour désigner un service sans demander de précision (un mot-clé un peu déformé vaut 6). */
    private const SCORE_SUR = 6;

    public function __construct(private OrientationServices $orientation) {}

    /**
     * @return Reponse
     */
    public function repondre(string $message): array
    {
        if (Demarche::detecterUrgenceMedicale($message)) {
            return $this->reponse(
                ConversationAssistant::TYPE_URGENCE,
                __('Cela ressemble à une urgence médicale : appelez immédiatement les secours. Les numéros d’urgence et les établissements de santé sont ici.'),
                [['libelle' => __('Numéros d’urgence'), 'url' => route('urgences.index'), 'icone' => 'phone']],
            );
        }

        $mots = OrientationServices::mots($message);

        if ($mots === []) {
            return $this->reponse(
                ConversationAssistant::TYPE_PRECISION,
                __('Pouvez-vous préciser votre besoin en quelques mots ? Par exemple : « acte de naissance », « poubelle pas ramassée » ou « trou dans la route ».'),
                [['libelle' => __('Voir tous les services'), 'url' => route('services.index'), 'icone' => 'building-office-2']],
            );
        }

        $regle = $this->regle($mots);
        $recherche = $this->orientation->rechercher($message);
        $resultats = $recherche['resultats'];
        $premier = $resultats[0] ?? null;
        $second = $resultats[1] ?? null;
        $reconnu = $premier !== null && $premier['raisons'] !== [];
        $sur = $reconnu && $premier['score'] >= self::SCORE_SUR
            && ($second === null || $premier['score'] >= $second['score'] * 1.5 || $premier['score'] - $second['score'] >= 8);

        if ($regle !== null) {
            // Lien interne uniquement (« /page ») : jamais d'URL externe ni de « javascript: » dans la bulle.
            $lien = $regle->lien_url;
            $actions = [];

            if ($lien !== null && $regle->lien_libelle !== null && RegleAssistant::lienInterneValide($lien)) {
                $actions[] = ['libelle' => $regle->lien_libelle, 'url' => url($lien), 'icone' => 'arrow-right'];
            }

            $service = $sur ? Service::query()->find($premier['id']) : null;

            if ($service !== null) {
                $actions[] = ['libelle' => __('Service :').' '.$service->nom, 'url' => route('services.show', $service), 'icone' => 'building-office-2'];
            }

            return $this->reponse(ConversationAssistant::TYPE_REGLE, $regle->reponse, $actions, [], $service?->id);
        }

        if ($sur) {
            $service = Service::query()->find($premier['id']);

            if ($service !== null) {
                return $this->pourService($service, $recherche['suggestion']);
            }
        }

        $choix = $this->choix($resultats);

        if ($reconnu && $choix !== []) {
            return $this->reponse(
                ConversationAssistant::TYPE_PRECISION,
                __('Votre demande peut concerner plusieurs services. Lequel correspond le mieux ?'),
                [],
                $choix,
            );
        }

        $texte = $recherche['suggestion'] !== null
            ? __('Je n’ai pas bien compris. Vouliez-vous dire « :suggestion » ?', ['suggestion' => $recherche['suggestion']])
            : __('Je n’ai pas bien compris votre demande.');

        return $this->reponse(
            ConversationAssistant::TYPE_REPLI,
            $texte.' '.($choix !== [] ? __('Ces services peuvent peut-être vous aider, sinon écrivez à la mairie.') : __('Reformulez avec d’autres mots, ou écrivez à la mairie.')),
            [['libelle' => __('Voir tous les services'), 'url' => route('services.index'), 'icone' => 'building-office-2']],
            $choix,
        );
    }

    /**
     * Réponse qui oriente vers un service précis (après un choix de l'habitant ou un résultat sans ambiguïté).
     *
     * @return Reponse
     */
    public function pourService(Service $service, ?string $suggestion = null): array
    {
        $texte = $suggestion !== null ? __('Vous vouliez sans doute dire « :suggestion ».', ['suggestion' => $suggestion]).' ' : '';
        $texte .= __('Le service « :nom » peut vous aider.', ['nom' => $service->nom]);

        $actions = [['libelle' => __('Voir le service (horaires, pièces à fournir)'), 'url' => route('services.show', $service), 'icone' => 'building-office-2']];

        if ($service->estIndisponible()) {
            $texte .= ' '.__('Il est momentanément indisponible : sa fiche indique le retour prévu et une alternative.');
        } else {
            $actions[] = ['libelle' => __('Faire une démarche auprès de ce service'), 'url' => route('demarches.create', ['service' => $service->id]), 'icone' => 'document-plus'];
        }

        return $this->reponse(ConversationAssistant::TYPE_SERVICE, $texte, $actions, [], $service->id);
    }

    /**
     * Enregistre l'échange, anonymisé (ni utilisateur ni IP), pour que l'admin améliore mots-clés et règles.
     *
     * @param  Reponse  $reponse
     */
    public function journaliser(string $conversation, string $message, array $reponse): void
    {
        ConversationAssistant::query()->create([
            'conversation' => $conversation,
            'message' => ConversationAssistant::anonymiser($message),
            'type' => $reponse['type'],
            'service_id' => $reponse['service_id'],
        ]);
    }

    /**
     * Règle active dont une expression est entièrement présente dans le message (fautes légères tolérées) ; la plus précise gagne.
     *
     * @param  list<string>  $mots
     */
    private function regle(array $mots): ?RegleAssistant
    {
        $meilleure = null;
        $meilleurScore = 0;

        foreach (RegleAssistant::query()->where('actif', true)->get() as $regle) {
            foreach ($regle->expressions() as $expression) {
                $termes = OrientationServices::mots($expression);

                if ($termes === [] || count($termes) <= $meilleurScore) {
                    continue;
                }

                $toutPresent = array_reduce($termes, fn (bool $ok, string $terme): bool => $ok && self::contient($mots, $terme), true);

                if ($toutPresent) {
                    [$meilleure, $meilleurScore] = [$regle, count($termes)];
                }
            }
        }

        return $meilleure;
    }

    /**
     * @param  list<string>  $mots
     */
    private static function contient(array $mots, string $terme): bool
    {
        foreach ($mots as $mot) {
            if ($mot === $terme || (strlen($terme) >= 5 && abs(strlen($mot) - strlen($terme)) <= 1 && levenshtein($mot, $terme) <= 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jusqu'à 3 services proposés en boutons (les moins pertinents, sous la moitié du meilleur score, sont écartés).
     *
     * @param  list<array{id: int, score: int, raisons: list<string>}>  $resultats
     * @return list<Choix>
     */
    private function choix(array $resultats): array
    {
        $meilleur = $resultats[0]['score'] ?? 0;
        $ids = array_column(array_slice(array_filter($resultats, fn (array $r): bool => $meilleur <= $r['score'] * 2), 0, 3), 'id');

        if ($ids === []) {
            return [];
        }

        $noms = Service::query()->whereIn('id', $ids)->pluck('nom', 'id');
        $choix = [];

        foreach ($ids as $id) {
            if (isset($noms[$id])) {
                $choix[] = ['id' => (int) $id, 'nom' => (string) $noms[$id]];
            }
        }

        return $choix;
    }

    /**
     * Ajoute toujours « Contacter la mairie » (D04) en dernière action : l'habitant n'est jamais sans issue.
     *
     * @param  list<Action>  $actions
     * @param  list<Choix>  $choix
     * @return Reponse
     */
    private function reponse(string $type, string $texte, array $actions, array $choix = [], ?int $serviceId = null): array
    {
        $actions[] = ['libelle' => __('Contacter la mairie'), 'url' => route('messages.create'), 'icone' => 'envelope'];

        return ['type' => $type, 'texte' => $texte, 'actions' => $actions, 'choix' => $choix, 'service_id' => $serviceId];
    }
}
