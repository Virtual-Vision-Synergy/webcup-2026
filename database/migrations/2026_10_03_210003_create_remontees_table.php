<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| F51 : remontées d'inquiétudes des habitants sur l'usage de leurs données.
| user_id est nullable + nullOnDelete : à la suppression du compte, la remontée est conservée
| anonymisée (sans nom ni e-mail) pour garder la trace de son traitement.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remontees', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('categorie', 50)->index();
            $table->string('objet');
            $table->text('message');
            $table->string('statut', 50)->default('recue')->index();
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamp('prise_en_compte_le')->nullable();
            $table->foreignId('pris_en_charge_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reponse')->nullable();
            $table->timestamp('repondue_le')->nullable();
            $table->foreignId('repondue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cloturee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remontees');
    }
};
