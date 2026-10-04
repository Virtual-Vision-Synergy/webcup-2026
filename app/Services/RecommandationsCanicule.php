<?php

namespace App\Services;

use App\Models\AlerteCanicule;

/**
 * Recommandations canicule adaptées à un profil.
 *
 * L'IA (OpenRouter, côté serveur) rédige le texte ; si elle est absente ou en échec,
 * on renvoie un texte fixe validé par l'Agence sanitaire.
 */
class RecommandationsCanicule
{
    public const SYSTEM_PROMPT = 'Tu es un conseiller de l\'Agence sanitaire de Nova Terra. '
        .'Réponds en français, en 4 à 6 puces courtes commençant par « - », concrètes et sans jargon, '
        .'adaptées au profil et au niveau d\'alerte indiqués. Pas d\'introduction ni de conclusion.';

    public const RECOMMANDATIONS_FIXES = [
        'personnes_agees' => [
            'Buvez régulièrement de l\'eau, même sans soif.',
            'Restez dans la pièce la plus fraîche et fermez les volets en journée.',
            'Rafraîchissez-vous plusieurs fois par jour (douche, linge humide).',
            'Faites-vous appeler ou rendre visite par un proche chaque jour.',
            'En cas de malaise, de confusion ou de fièvre, appelez les secours.',
        ],
        'enfants' => [
            'Faites boire l\'enfant très souvent, sans attendre qu\'il le demande.',
            'Habillez-le de vêtements légers et clairs, avec un chapeau dehors.',
            'Évitez les jeux et sports en plein soleil entre 11 h et 17 h.',
            'Ne laissez jamais un enfant seul dans un véhicule.',
            'Somnolence, peau rouge et chaude ou vomissements : consultez sans attendre.',
        ],
        'femmes_enceintes' => [
            'Buvez de l\'eau fréquemment tout au long de la journée.',
            'Évitez toute sortie aux heures les plus chaudes.',
            'Reposez-vous dans un lieu frais et aéré.',
            'Contactez votre médecin en cas de vertiges, de maux de tête ou de contractions.',
        ],
        'malades_chroniques' => [
            'Buvez régulièrement, sauf avis médical contraire.',
            'Gardez vos médicaments à l\'abri de la chaleur et suivez votre traitement.',
            'Demandez à votre médecin si votre traitement doit être adapté.',
            'Restez au frais et limitez tout effort physique.',
            'Appelez votre médecin ou les secours au moindre symptôme inhabituel.',
        ],
        'travailleurs_exterieur' => [
            'Décalez les tâches pénibles tôt le matin ou en fin de journée.',
            'Faites des pauses régulières à l\'ombre et buvez un verre d\'eau toutes les 20 minutes.',
            'Portez un couvre-chef et des vêtements légers et clairs.',
            'Surveillez vos collègues : crampes, nausées ou confusion imposent d\'arrêter le travail.',
        ],
    ];

    public function __construct(private readonly Ai $ai) {}

    /**
     * @return array{lignes: list<string>, source: 'ia'|'standard'}
     */
    public function pour(AlerteCanicule $alerte, string $profil): array
    {
        $texte = $this->ai->ask(self::SYSTEM_PROMPT, $this->prompt($alerte, $profil));
        $lignes = $texte === null ? [] : $this->extraireLignes($texte);

        if ($lignes !== []) {
            return ['lignes' => $lignes, 'source' => 'ia'];
        }

        return ['lignes' => self::RECOMMANDATIONS_FIXES[$profil] ?? [], 'source' => 'standard'];
    }

    private function prompt(AlerteCanicule $alerte, string $profil): string
    {
        return sprintf(
            'Secteur : %s. Niveau d\'alerte : %s. Température maximale prévue : %d °C. Profil : %s.',
            $alerte->secteur,
            AlerteCanicule::NIVEAU_LABELS[$alerte->niveau] ?? $alerte->niveau,
            $alerte->temperature_max,
            AlerteCanicule::PROFIL_LABELS[$profil] ?? $profil,
        );
    }

    /**
     * @return list<string>
     */
    private function extraireLignes(string $texte): array
    {
        $lignes = [];

        foreach (preg_split('/\R/', $texte) ?: [] as $ligne) {
            $ligne = trim((string) preg_replace('/^[\s\-\*\•\d\.\)]+/u', '', strip_tags($ligne)));

            if ($ligne !== '') {
                $lignes[] = mb_substr($ligne, 0, 300);
            }
        }

        return array_slice($lignes, 0, 8);
    }
}
