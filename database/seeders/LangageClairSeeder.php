<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F89 : versions en langage clair des services principaux, rédigées et validées par la mairie (sans IA).
 * Idempotent : un service qui a déjà une version en langage clair n'est pas modifié (un agent a pu la corriger).
 * Appelé par DatabaseSeeder (démo locale) et par la migration 2026_10_04_150004 (production, sans db:seed).
 */
class LangageClairSeeder extends Seeder
{
    /** @var array<string, string> Nom du service => version en langage clair. */
    public const SERVICES = [
        'État civil' => "Ce service vous donne les papiers officiels de la famille : acte de naissance, de mariage ou de décès, livret de famille.\nIl peut aussi certifier votre signature et prouver que vous habitez à Nova Terra.\nPour un mariage, prenez rendez-vous avant de venir.\nApportez votre pièce d'identité.",
        'Urbanisme' => "Vous voulez construire, agrandir ou faire des travaux sur votre maison ? Ce service vous dit si c'est possible et vous donne l'autorisation.\nUn architecte vous aide gratuitement : prenez rendez-vous.\nLe mardi et le vendredi, on vous reçoit seulement sur rendez-vous.",
        'Services techniques et voirie' => "Ce service répare les routes, les trottoirs et les lampadaires. Il s'occupe aussi des espaces verts et ramasse les gros objets (meubles, appareils).\nUn trou dans la route ou un lampadaire en panne ? Signalez-le : une équipe vient sous 3 jours (72 heures).",
        'Action sociale (CCAS)' => "Ce service aide les familles en difficulté, les personnes âgées et les personnes handicapées.\nIl peut donner une aide d'urgence, livrer des repas à domicile, ou vous donner une adresse pour recevoir votre courrier.\nLe mercredi matin, vous pouvez venir sans rendez-vous.",
        'Petite enfance et écoles' => "Ce service inscrit votre enfant à la crèche ou à l'école publique.\nIl gère aussi la cantine, la garderie avant et après l'école, et le bus scolaire.",
        'Accueil de la Mairie' => "C'est la porte d'entrée de la mairie : on vous écoute et on vous dit à quel service vous adresser.\nSi vous ne savez pas par où commencer, venez ici en premier.",
    ];

    public function run(): void
    {
        $maintenant = now();

        foreach (self::SERVICES as $nom => $texte) {
            DB::table('services')
                ->where('nom', $nom)
                ->whereNull('langage_clair')
                ->update(['langage_clair' => $texte, 'langage_clair_valide_le' => $maintenant]);
        }
    }
}
