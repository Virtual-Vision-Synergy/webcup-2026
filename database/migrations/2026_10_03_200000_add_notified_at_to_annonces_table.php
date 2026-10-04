<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date d'envoi de la notification aux habitants (F30) : une annonce n'est jamais notifiée deux fois.
     */
    public function up(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
