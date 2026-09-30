<?php

namespace App\Console\Commands;

use App\Mail\TestMail as TestMailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TestMail extends Command
{
    protected $signature = 'app:test-mail {email : Adresse qui recevra le message}';

    protected $description = 'Envoie un e-mail de test pour vérifier la configuration (à lancer sur le serveur, sans tinker)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error("Adresse e-mail invalide : {$email}");

            return self::FAILURE;
        }

        $this->line('Transport : '.config('mail.default').' — expéditeur : '.config('mail.from.address'));

        try {
            Mail::to($email)->send(new TestMailMessage);
        } catch (Throwable $e) {
            $this->error('Échec de l\'envoi : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("E-mail de test envoyé à {$email}.");

        return self::SUCCESS;
    }
}
