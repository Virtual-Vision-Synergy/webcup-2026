<?php

use App\Http\Controllers\Auth\LienConnexionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// D02 : connexion sans mot de passe par lien envoyé par e-mail (pages publiques réservées aux invités).
Route::middleware('guest')->group(function () {
    Route::get('connexion/lien', [LienConnexionController::class, 'create'])->name('login-link.create');
    Route::post('connexion/lien', [LienConnexionController::class, 'store'])->name('login-link.store');
    Route::get('connexion/lien/{lien}', [LienConnexionController::class, 'show'])->name('login-link.show');
    Route::post('connexion/lien/{lien}', [LienConnexionController::class, 'consume'])->name('login-link.consume');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/features.php';
require __DIR__.'/agent.php';
