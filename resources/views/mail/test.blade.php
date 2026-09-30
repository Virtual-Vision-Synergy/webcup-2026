<x-mail::message>
# {{ __('Hello!') }}

Si vous lisez ce message, l'envoi d'e-mails de **{{ config('app.name') }}** fonctionne.

Envoyé le {{ now()->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}.

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
