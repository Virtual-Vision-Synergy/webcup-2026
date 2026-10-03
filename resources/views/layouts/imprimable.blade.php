{{--
    Gabarit des documents à imprimer ou à enregistrer en PDF (F56) : sans menu, fond clair forcé.
--}}
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex" />
        <title>{{ filled($title ?? null) ? __($title).' · Terra Nova' : 'Terra Nova' }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @page { size: A4; margin: 15mm; }
        </style>
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased print:min-h-0">
        <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 print:max-w-none print:p-0">
            {{ $slot }}
        </main>
    </body>
</html>
