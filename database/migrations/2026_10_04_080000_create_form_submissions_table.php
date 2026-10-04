<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F82 : envois de formulaires déjà traités (jeton unique + empreinte du contenu) pour refuser les doublons.
 * Migration additive : une nouvelle table. Aucune donnée saisie n'est conservée (seulement son empreinte sha256).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('form_key', 30);
            $table->uuid('token')->unique();
            $table->char('content_hash', 64);
            $table->nullableMorphs('submittable');
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'form_key', 'content_hash', 'created_at'], 'form_submissions_doublon_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
