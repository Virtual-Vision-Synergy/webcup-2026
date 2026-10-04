<?php

use Database\Seeders\PartnerOfferingSeeder;
use Database\Seeders\PartnerSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données uniquement (F99) : services partenaires de démonstration, comme en local (la production ne lance jamais db:seed).
 * Les partenaires F74 de démo ne sont créés qu'en production et si la table est vide (jamais d'écrasement d'un partenaire
 * saisi par un agent ; en local ils viennent du seed, et les tests partent d'une base sans partenaire : rien n'est ajouté).
 * En production, aucun compte n'est créé (PartnerOfferingSeeder ne crée les comptes de démo qu'hors production).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->isProduction() && DB::table('partners')->doesntExist()) {
            (new PartnerSeeder)->run();
        }

        (new PartnerOfferingSeeder)->run();
    }

    public function down(): void
    {
        // Rien à défaire : les services sont ensuite tenus à jour par les partenaires.
    }
};
