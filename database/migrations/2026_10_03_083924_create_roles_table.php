<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les 3 rôles sont insérés ici (et non par un seeder) : la production ne lance jamais db:seed.
     * Les id sont fixes : users.role_id vaut 1 (citoyen) par défaut.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label');
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['id' => 1, 'code' => 'citoyen', 'label' => 'Citoyen', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'agent', 'label' => 'Agent municipal', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'admin', 'label' => 'Administrateur', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
