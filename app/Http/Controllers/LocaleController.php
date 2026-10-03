<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Mémorise la langue choisie (session + cookie d'un an) puis revient à la page précédente.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('app.available_locales')), 404);

        $request->session()->put('locale', $locale);

        return back()->withCookie(Cookie::make('locale', $locale, 60 * 24 * 365));
    }
}
