<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Choix de la langue (public : valable aussi avant connexion), mémorisé en session et en cookie.
Route::post('langue/{locale}', LocaleController::class)->middleware('throttle:30,1')->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/features.php';
require __DIR__.'/agent.php';
