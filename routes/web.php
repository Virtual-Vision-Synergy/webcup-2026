<?php

use App\Http\Controllers\Auth\ActivationCompteController;
use App\Http\Controllers\Auth\LienConnexionController;
use App\Http\Middleware\DefinirLangue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// F51 : page publique décidée — un habitant doit comprendre l'usage de ses données avant de créer un compte.
Route::view('vos-donnees', 'vos-donnees')->name('privacy.show');

// D20 : page publique décidée — les aides d'accessibilité doivent être connues et réglables avant l'inscription.
Route::view('accessibilite', 'accessibilite')->name('accessibility.show');

Route::get('langue/{code}', function (string $code, Request $request) {
    abort_unless(array_key_exists($code, DefinirLangue::LANGUES), 404);

    $request->session()->put('langue', $code);

    // F71 : langue mémorisée durablement sur cet appareil, y compris sur l'écran de connexion.
    return redirect()->back(fallback: route('home'))->withCookie(cookie()->forever(DefinirLangue::COOKIE, $code));
})->middleware('throttle:30,1')->name('langue');

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
