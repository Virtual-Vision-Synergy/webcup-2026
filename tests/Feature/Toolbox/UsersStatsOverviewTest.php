<?php

use App\Filament\Widgets\UsersStatsOverview;
use App\Models\User;
use Livewire\Livewire;

test('le widget affiche inscrits, administrateurs et nouveaux des 7 derniers jours', function () {
    User::factory()->admin()->count(2)->create();
    User::factory()->count(3)->create();
    User::factory()->count(4)->create(['created_at' => now()->subDays(10)]);

    $this->actingAs(User::factory()->admin()->create(['created_at' => now()->subDays(30)]));

    // 10 inscrits, 3 admins, 5 nouveaux (les 2 + 3 créés à l'instant)
    Livewire::test(UsersStatsOverview::class)
        ->assertSeeInOrder(['Inscrits', '10', 'Administrateurs', '3', 'Nouveaux (7 jours)', '5']);
});

test('le widget est réservé aux admins', function () {
    $this->actingAs(User::factory()->create());
    expect(UsersStatsOverview::canView())->toBeFalse();

    $this->actingAs(User::factory()->admin()->create());
    expect(UsersStatsOverview::canView())->toBeTrue();
});

test('le widget apparaît sur le tableau de bord admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(UsersStatsOverview::class);
});
