<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quartiers de Nova Terra (F29) : référentiel créé ici pour exister aussi en production (pas de seeder en ligne).
     */
    public function up(): void
    {
        Schema::create('quartiers', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        DB::table('quartiers')->insert(collect(['Nord', 'Sud', 'Est', 'Ouest', 'Centre'])
            ->map(fn (string $nom): array => ['nom' => $nom, 'slug' => strtolower($nom), 'created_at' => now(), 'updated_at' => now()])
            ->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('quartiers');
    }
};
