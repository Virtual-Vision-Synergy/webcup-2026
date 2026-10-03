<?php

use App\Models\Traduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('langue/{code}', function (string $code, Request $request) {
    if (! in_array($code, array_keys(Traduction::LANGUES), true)) {
        abort(404);
    }

    $request->session()->put('langue', $code);

    return back(fallback: route('home'));
})->name('langue');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/features.php';
require __DIR__.'/agent.php';
