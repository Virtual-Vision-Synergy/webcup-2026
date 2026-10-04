<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F99 : « Me prévenir quand disponible » (un abonnement par habitant et par service partenaire).
     */
    public function up(): void
    {
        Schema::create('availability_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_offering_id')->constrained()->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'partner_offering_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_subscriptions');
    }
};
