<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail de contrôle : sert à vérifier que l'envoi fonctionne (voir `php artisan app:test-mail`).
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Test e-mail').' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
