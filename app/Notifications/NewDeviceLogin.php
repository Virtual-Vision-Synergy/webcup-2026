<?php

namespace App\Notifications;

use App\Models\KnownDevice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * F54 : connexion depuis un nouvel appareil (cloche « Avis » + e-mail), envoyée en file d'attente
 * pour ne pas ralentir la connexion. Les clés sujet / lignes / libelle / url sont celles de la cloche (F30).
 */
class NewDeviceLogin extends Notification implements ShouldQueue
{
    use Queueable;

    public const SUJET = 'Nouvelle connexion à votre compte depuis un nouvel appareil';

    public const LIBELLE_ACTION = 'Ce n’était pas moi';

    /** Durée de validité du lien « Ce n'était pas moi » envoyé par e-mail. */
    public const VALIDITE_LIEN_HEURES = 24;

    public function __construct(public KnownDevice $device, public CarbonInterface $connecteLe) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * « samedi 3 octobre 2026 »
     */
    public function date(): string
    {
        return $this->connecteLe->copy()->timezone(KnownDevice::FUSEAU)->locale('fr')->translatedFormat('l j F Y');
    }

    /**
     * « 14 h 32 »
     */
    public function heure(): string
    {
        return $this->connecteLe->copy()->timezone(KnownDevice::FUSEAU)->format('G \h i');
    }

    public function message(): string
    {
        return 'Nouvelle connexion à votre compte le '.$this->date().' à '.$this->heure().' (heure de Nova Terra) depuis '
            .$this->device->libelleAppareil().', adresse approximative '.$this->device->ipAffichee().'. '
            .'Si c’était vous, vous n’avez rien à faire. Sinon, cliquez sur « '.self::LIBELLE_ACTION.' ».';
    }

    /**
     * Lien e-mail : URL signée temporaire, liée à l'appareil et à son propriétaire. Elle ouvre une confirmation (GET).
     */
    public function lienSigne(): string
    {
        return URL::temporarySignedRoute(
            'profile.devices.report',
            now()->addHours(self::VALIDITE_LIEN_HEURES),
            ['knownDevice' => $this->device->id, 'user' => $this->device->user_id],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(self::SUJET)
            ->greeting('Nouvelle connexion à votre compte')
            ->line('Quelqu’un vient de se connecter à votre compte depuis un appareil que nous ne connaissions pas.')
            ->line('Date : '.Str::ucfirst($this->date()))
            ->line('Heure : '.$this->heure().' (heure de Nova Terra)')
            ->line('Appareil : '.$this->device->os.' ('.$this->device->device_type.')')
            ->line('Navigateur : '.$this->device->browser)
            ->line('Adresse approximative : '.$this->device->ipAffichee())
            ->line('Si c’était vous, vous n’avez rien à faire.')
            ->line('Sinon, cliquez sur le bouton ci-dessous : les autres appareils seront déconnectés et vous pourrez changer votre mot de passe.')
            ->action(self::LIBELLE_ACTION, $this->lienSigne())
            ->line('Ce lien est valable '.self::VALIDITE_LIEN_HEURES.' heures.')
            ->salutation('Le Haut Conseil de la Ville');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'nouvel_appareil',
            'sujet' => self::SUJET,
            'lignes' => [$this->message()],
            'libelle' => self::LIBELLE_ACTION,
            'url' => route('profile.devices.confirm', $this->device, false),
            'known_device_id' => $this->device->id,
            'date' => $this->date(),
            'heure' => $this->heure(),
            'appareil' => $this->device->libelleAppareil(),
            'navigateur' => $this->device->browser,
            'systeme' => $this->device->os,
            'ip_approx' => $this->device->ipAffichee(),
        ];
    }
}
