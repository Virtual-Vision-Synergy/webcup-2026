<?php

use App\Http\Controllers\Auth\ActivationCompteController;
use App\Http\Controllers\Auth\LienConnexionController;
use App\Http\Middleware\DefinirLangue;
use App\Support\ModeAllege;
use App\Support\VersionSimple;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// F93 : page publique décidée — affichée sans réseau à tous (connectés ou non) par le service worker ; aucune donnée personnelle.
Route::view('hors-ligne', 'hors-ligne')->name('hors-ligne');

// F51 : page publique décidée — un habitant doit comprendre l'usage de ses données avant de créer un compte.
Route::view('vos-donnees', 'vos-donnees')->name('privacy.show');

// D20 : page publique décidée — les aides d'accessibilité doivent être connues et réglables avant l'inscription.
Route::view('accessibilite', 'accessibilite')->name('accessibility.show');

// F95 : page publique décidée — les mesures de sobriété (requêtes, poids des pages) sont consultables sans compte.
Route::view('sobriete', 'sobriete')->name('sobriete.show');

Route::get('langue/{code}', function (string $code, Request $request) {
    abort_unless(array_key_exists($code, DefinirLangue::LANGUES), 404);

    $request->session()->put('langue', $code);

    // F71 : langue mémorisée durablement sur cet appareil, y compris sur l'écran de connexion.
    return redirect()->back(fallback: route('home'))->withCookie(cookie()->forever(DefinirLangue::COOKIE, $code));
})->middleware('throttle:30,1')->name('langue');

// F59 : page publique décidée — le « Mode allégé » doit être activable avant la connexion (accueil sur réseau lent).
// F96 : « actif » (0 ou 1) fixe le choix explicite (bandeau de la version légère automatique) ; sinon bascule.
Route::post('mode-allege', function (Request $request) {
    $actif = in_array($request->input('actif'), ['0', '1'], true)
        ? $request->input('actif') === '1'
        : ! ModeAllege::actif($request);
    $user = $request->user();

    if ($user !== null) {
        $user->forceFill(['mode_allege' => $actif])->save();
    }

    return redirect()->back(fallback: route('home'))
        ->withCookie(cookie()->forever(ModeAllege::COOKIE, $actif ? '1' : '0'));
})->middleware('throttle:30,1')->name('mode-allege');

// F62 : page publique décidée — la « Version simple » doit être disponible dès l'accueil, avant la connexion.
Route::post('version-simple', function (Request $request) {
    $actif = ! VersionSimple::actif($request);
    $user = $request->user();

    if ($user !== null) {
        $user->forceFill(['version_simple' => $actif])->save();
    }

    return redirect()->back(fallback: route('home'))
        ->withCookie(cookie()->forever(VersionSimple::COOKIE, $actif ? '1' : '0'));
})->middleware('throttle:30,1')->name('version-simple');

// D02 : connexion sans mot de passe par lien envoyé par e-mail (pages publiques réservées aux invités).
Route::middleware('guest')->group(function () {
    Route::get('connexion/lien', [LienConnexionController::class, 'create'])->name('login-link.create');
    Route::post('connexion/lien', [LienConnexionController::class, 'store'])->name('login-link.store');
    Route::get('connexion/lien/{lien}', [LienConnexionController::class, 'show'])->name('login-link.show');
    Route::post('connexion/lien/{lien}', [LienConnexionController::class, 'consume'])->name('login-link.consume');

    // F71 : première connexion d'un habitant sans e-mail (identifiant + code d'activation à usage unique).
    Route::get('activer', [ActivationCompteController::class, 'create'])->name('activation.create');
    Route::post('activer', [ActivationCompteController::class, 'store'])->middleware('throttle:10,1')->name('activation.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/features.php';
require __DIR__.'/agent.php';
