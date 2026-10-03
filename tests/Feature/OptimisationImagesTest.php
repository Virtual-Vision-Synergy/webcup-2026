<?php

use App\Models\Signalement;
use App\Models\User;
use App\Services\OptimiseurImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('une grande photo est réduite à 1600 px, convertie en WebP et accompagnée d\'une miniature', function () {
    $chemin = app(OptimiseurImage::class)->enregistrer(UploadedFile::fake()->image('photo.jpg', 3200, 2400), 'signalements');

    expect($chemin)->toEndWith('_1600x1200.webp');
    Storage::disk('public')->assertExists($chemin);

    [$largeur, $hauteur, $type] = getimagesize(Storage::disk('public')->path($chemin));
    expect([$largeur, $hauteur, $type])->toBe([1600, 1200, IMAGETYPE_WEBP]);

    $miniature = OptimiseurImage::miniature($chemin);
    Storage::disk('public')->assertExists($miniature);
    expect(getimagesize(Storage::disk('public')->path($miniature))[0])->toBe(OptimiseurImage::LARGEUR_MINIATURE);
});

test('une petite image n\'est pas agrandie et n\'a pas de miniature', function () {
    $chemin = app(OptimiseurImage::class)->enregistrer(UploadedFile::fake()->image('petite.png', 500, 300), 'actualites');

    expect($chemin)->toEndWith('_500x300.webp')
        ->and(OptimiseurImage::miniature($chemin))->toBeNull();
});

test('supprimer efface l\'image et sa miniature', function () {
    $optimiseur = app(OptimiseurImage::class);
    $chemin = $optimiseur->enregistrer(UploadedFile::fake()->image('photo.jpg', 2000, 1000), 'signalements');
    $miniature = OptimiseurImage::miniature($chemin);

    $optimiseur->supprimer($chemin);

    Storage::disk('public')->assertMissing($chemin);
    Storage::disk('public')->assertMissing($miniature);
});

test('la photo d\'un signalement est optimisée à l\'enregistrement et affichée en différé', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::signalements.form')
        ->set('categorie', 'voirie')
        ->set('description', 'Nid-de-poule profond devant l\'école.')
        ->set('lieu', 'Rue des Lumières')
        ->set('photo', UploadedFile::fake()->image('nid.jpg', 3200, 2400))
        ->call('save')
        ->assertHasNoErrors();

    $signalement = Signalement::where('user_id', $user->id)->firstOrFail();
    expect($signalement->photo)->toStartWith('signalements/')->toEndWith('_1600x1200.webp');

    $this->actingAs($user)
        ->get(route('signalements.show', $signalement))
        ->assertOk()
        ->assertSee('loading="lazy"', false)
        ->assertSee('width="1600" height="1200"', false)
        ->assertSee('640w', false);
});

test('un autre utilisateur ne peut pas remplacer la photo d\'un signalement', function () {
    $signalement = Signalement::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::signalements.form', ['signalement' => $signalement])
        ->assertForbidden();
});
