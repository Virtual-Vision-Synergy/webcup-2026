<?php

namespace Database\Seeders;

use App\Models\Quartier;
use Illuminate\Database\Seeder;

class QuartierSeeder extends Seeder
{
    /**
     * Quartiers de base de Nova Terra (déjà créés par la migration ; relançable sans doublon).
     */
    public function run(): void
    {
        foreach (Quartier::DE_BASE as $nom => $slug) {
            Quartier::firstOrCreate(['slug' => $slug], ['nom' => $nom]);
        }
    }
}
