<?php

use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use App\Services\Sauvegardes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->dossier = storage_path('framework/testing/sauvegardes-'.uniqid());
    File::ensureDirectoryExists($this->dossier);
    config(['services.sauvegardes.dossier' => $this->dossier]);
});

afterEach(function () {
    File::deleteDirectory($this->dossier);
});

test('le vérificateur lit les sauvegardes de BACKUP_DIR et compte les n-uplets des INSERT étendus de mysqldump', function () {
    User::factory()->count(3)->create();

    // Extrait réel de mysqldump : un seul INSERT par table, plusieurs n-uplets, chaînes contenant « ),( » et « \' ».
    $extrait = <<<'SQL'
        -- MariaDB dump 10.19  Distrib 10.11.18-MariaDB, for Linux (x86_64)
        --
        -- Host: localhost    Database: virtualvisionsy_webcup
        -- ------------------------------------------------------
        DROP TABLE IF EXISTS `users`;
        CREATE TABLE `users` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        LOCK TABLES `users` WRITE;
        /*!40000 ALTER TABLE `users` DISABLE KEYS */;
        INSERT INTO `users` VALUES (1,'Rakoto (admin)','2026-10-03 09:00:00'),(2,'L\'agent ),( du guichet',NULL),(3,'Voahangy','2026-10-03 10:12:00');
        /*!40000 ALTER TABLE `users` ENABLE KEYS */;
        UNLOCK TABLES;
        -- Dump completed on 2026-10-04  8:30:01
        SQL;

    File::put($this->dossier.'/virtualvisionsy_webcup-20261004-083001.sql.gz', gzencode($extrait."\n"));

    $service = app(Sauvegardes::class);

    expect($service->derniere()['nom'])->toBe('virtualvisionsy_webcup-20261004-083001.sql.gz')
        ->and($service->etat()['libelle'])->not->toBe('Aucune sauvegarde trouvée');

    $verification = $service->verifierDerniere();

    expect($verification->details['importantes']['users'])->toMatchArray(['sauvegarde' => 3, 'base' => 3])
        ->and($verification->details['termine'])->toBeTrue()
        ->and($verification->rapport)->toContain('3 comptes')
        ->and($verification->rapport)->not->toContain('vide : comptes');
});

test('la date de la dernière sauvegarde est affichée en heure de Madagascar', function () {
    $chemin = $this->dossier.'/virtualvisionsy_webcup-20261004-053000.sql.gz';
    File::put($chemin, gzencode("-- Dump completed\n"));
    touch($chemin, strtotime('2026-10-04 05:30:00 UTC'));

    expect(app(Sauvegardes::class)->derniere()['date']->format('d/m/Y H:i'))->toBe('04/10/2026 08:30')
        ->and(FilamentTimezone::get())->toBe('Indian/Antananarivo');
});

test('un citoyen ne peut pas ouvrir la page des sauvegardes', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get('/admin/sauvegardes')
        ->assertForbidden();
});
