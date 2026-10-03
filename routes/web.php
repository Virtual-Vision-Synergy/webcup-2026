<?php

use App\Http\Middleware\DefinirLangue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::view('/', 'welcome')->name('home');

Route::post('langue', function (Request $request) {
    $validated = $request->validate(['langue' => ['required', Rule::in(array_keys(DefinirLangue::LANGUES))]]);
    $request->session()->put('langue', $validated['langue']);

    return redirect()->back(fallback: route('home'));
})->middleware('throttle:30,1')->name('langue');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/features.php';
require __DIR__.'/agent.php';
