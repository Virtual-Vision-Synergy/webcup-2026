<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F37 : alerte envoyée au titulaire d'un compte bloqué après plusieurs échecs de connexion.
 * L'adresse IP est masquée partiellement.
 */
class TentativesConnexionSuspectes extends Notification
{
    public function __construct(
        public CarbonInterface $date,
        public string $ip,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public static function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.x.x';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return implode(':', array_slice(explode(':', $ip), 0, 2)).':…';
        }

        return 'inconnue';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tentatives de connexion suspectes sur votre compte')
            ->greeting('Bonjour,')
            ->line($this->texte())
            ->line('Si ce n’était pas vous, nous vous conseillons de changer votre mot de passe.')
            ->action('Changer mon mot de passe', route('password.request'))
            ->line('Si c’était bien vous, aucune action n’est nécessaire : l’accès sera rétabli automatiquement.');
    }

    /**
     * Même format que la notification générique Avis (affichée par la cloche).
     *
     * @return array{sujet: string, lignes: array<int, string>, libelle: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sujet' => 'Tentatives de connexion suspectes',
            'lignes' => [$this->texte(), 'Si ce n’était pas vous, changez votre mot de passe.'],
            'libelle' => 'Changer mon mot de passe',
            'url' => route('password.request'),
        ];
    }

    private function texte(): string
    {
        return 'Plusieurs tentatives de connexion échouées ont eu lieu sur votre compte le '
            .$this->date->timezone(config('app.timezone'))->format('d/m/Y à H:i')
            .' depuis l’adresse '.self::maskIp($this->ip).'. Par sécurité, la connexion a été temporairement bloquée.';
    }
}
