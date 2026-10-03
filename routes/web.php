<?php

use App\Http\Middleware\DefinirLangue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('langue/{code}', function (string $code, Request $request) {
    abort_unless(array_key_exists($code, DefinirLangue::LANGUES), 404);

    $request->session()->put('langue', $code);

    return redirect()->back(fallback: route('home'));
})->middleware('throttle:30,1')->name('langue');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/features.php';
require __DIR__.'/agent.php';
