<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F99 : services proposés par les partenaires (F74) dans le catalogue.
     * opening_hours : même format que partners.opening_hours ; vide = horaires du partenaire.
     */
    public function up(): void
    {
        Schema::create('partner_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('conditions')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('status', 20)->default('available')->index();
            $table->date('unavailable_until')->nullable();
            $table->string('booking_url')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('alternative_text')->nullable();
            $table->string('alternative_url')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_offerings');
    }
};
