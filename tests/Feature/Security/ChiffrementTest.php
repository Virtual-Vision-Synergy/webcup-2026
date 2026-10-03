<?php

use App\Models\AuditLog;
use App\Models\Signalement;
use App\Models\User;
use App\Services\OptimiseurImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| F69 (A2, A3, A4, A6) : téléphone chiffré au repos, photos de signalement privées et chiffrées.
*/

test('A4 : le téléphone est illisible en SQL brut mais lisible via le modèle', function () {
    $user = User::factory()->create(['telephone' => '0341234567']);

    $ligne = DB::table('users')->where('id', $user->id)->first();

    expect($ligne->telephone)->toBeNull()
        ->and($ligne->telephone_chiffre)->not->toContain('0341234567')
        ->and(Crypt::decryptString($ligne->telephone_chiffre))->toBe('0341234567')
        ->and($ligne->telephone_hash)->toBe(User::hashTelephone('034 12 345 67'))
        ->and($user->fresh()->telephone)->toBe('0341234567');
});

test('A4 : le téléphone est absent des réponses JSON du modèle', function () {
    $json = User::factory()->create(['telephone' => '0341234567'])->toJson();

    expect($json)->not->toContain('0341234567')
        ->and($json)->not->toContain('telephone');
});

test('A4 : un changement de téléphone apparaît « [masqué] » dans le journal d’audit', function () {
    $user = User::factory()->create(['telephone' => '0341111111']);

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('telephone', '034 22 222 22')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $entree = AuditLog::query()->where('subject_type', 'User')->where('subject_id', $user->id)->where('action', 'updated')->latest('id')->firstOrFail();

    expect(json_encode($entree->changes))->not->toContain('0342222222')
        ->and($entree->changes['telephone_chiffre']['apres'])->toBe(AuditLog::MASQUE);
});

test('A4 : la connexion par numéro de téléphone fonctionne toujours', function () {
    User::factory()->create(['email' => 'habitant@example.com', 'telephone' => '0341234567']);

    $this->post(route('login.store'), ['email' => '034 12 345 67', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('A6 : un habitant ne peut pas reprendre le numéro de téléphone d’un autre compte', function () {
    User::factory()->create(['telephone' => '0341234567']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.profile')
        ->set('telephone', '034 12 345 67')
        ->call('updateProfileInformation')
        ->assertHasErrors(['telephone']);
});

describe('photos de signalement', function () {
    beforeEach(function () {
        Storage::fake('public');
        Storage::fake('local');
    });

    test('A2 : la photo est stockée chiffrée sur le disque privé, jamais sur le disque public', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::signalements.form')
            ->set('categorie', 'voirie')
            ->set('description', 'Trou dans la chaussée.')
            ->set('lieu', 'Rue des Lumières')
            ->set('photo', UploadedFile::fake()->image('trou.jpg', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $chemin = Signalement::where('user_id', $user->id)->value('photo');

        expect($chemin)->toStartWith(OptimiseurImage::PREFIXE_PRIVE)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
        Storage::disk('local')->assertExists($chemin);
        expect(Storage::disk('local')->get($chemin))->not->toStartWith('RIFF')
            ->and(OptimiseurImage::lire($chemin))->toStartWith('RIFF');
    });

    test('A3 : un fichier PHP renommé en .jpg est refusé et rien n’est stocké', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::signalements.form')
            ->set('categorie', 'voirie')
            ->set('description', 'Trou dans la chaussée.')
            ->set('lieu', 'Rue des Lumières')
            ->set('photo', UploadedFile::fake()->createWithContent('photo.jpg', '<?php system($_GET["c"]); ?>'))
            ->call('save')
            ->assertHasErrors(['photo']);

        expect(Signalement::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    test('A2 : la photo est servie à l’auteur et à un agent, refusée à un autre habitant et aux invités', function () {
        $user = User::factory()->create();
        $chemin = app(OptimiseurImage::class)->enregistrerPrive(UploadedFile::fake()->image('trou.jpg', 300, 200), 'signalements');
        $signalement = Signalement::factory()->for($user)->create(['photo' => $chemin]);

        $this->get(route('signalements.photo', $signalement))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->citoyen()->create())->get(route('signalements.photo', $signalement))->assertForbidden();
        $this->actingAs($user)->get(route('signalements.photo', $signalement))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs(User::factory()->agent()->create())->get(route('signalements.photo', $signalement))->assertOk();
    });

    test('security:migrer-photos chiffre et déplace une ancienne photo publique', function () {
        Storage::disk('public')->put('signalements/ancienne_10x10.webp', 'contenu-image');
        $signalement = Signalement::factory()->create(['photo' => 'signalements/ancienne_10x10.webp']);

        $this->artisan('security:migrer-photos')->assertSuccessful();

        expect($signalement->fresh()->photo)->toBe('prive/signalements/ancienne_10x10.webp')
            ->and(OptimiseurImage::lire('prive/signalements/ancienne_10x10.webp'))->toBe('contenu-image');
        Storage::disk('public')->assertMissing('signalements/ancienne_10x10.webp');
    });
});
