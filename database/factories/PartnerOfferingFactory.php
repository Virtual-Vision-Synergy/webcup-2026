<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerOffering;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PartnerOffering>
 */
class PartnerOfferingFactory extends Factory
{
    /**
     * Par défaut : publié, disponible, horaires du partenaire, contact par téléphone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake('fr_FR')->sentence(3), '.');

        return [
            'partner_id' => Partner::factory(),
            'created_by' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake('fr_FR')->paragraph(),
            'conditions' => 'Ouvert à tous les habitants de Nova Terra. Gratuit.',
            'opening_hours' => null,
            'status' => PartnerOffering::STATUS_AVAILABLE,
            'unavailable_until' => null,
            'booking_url' => null,
            'contact_phone' => '+261 20 00 000 10',
            'contact_email' => null,
            'alternative_text' => null,
            'alternative_url' => null,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function full(): static
    {
        return $this->state(['status' => PartnerOffering::STATUS_FULL]);
    }

    public function unavailable(?string $until = null): static
    {
        return $this->state(['status' => PartnerOffering::STATUS_UNAVAILABLE, 'unavailable_until' => $until]);
    }

    public function bookable(string $url = 'https://reservation.example.org/creneaux'): static
    {
        return $this->state(['booking_url' => $url]);
    }

    public function withAlternative(): static
    {
        return $this->state([
            'alternative_text' => 'En attendant, l\'épicerie solidaire du quartier accueille les familles le samedi matin.',
            'alternative_url' => 'https://alternative.example.org',
        ]);
    }
}
