<?php

namespace App\Console\Commands;

use App\Models\Signalement;
use App\Services\OptimiseurImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * F69 (A2) : déplace les photos de signalement encore sur le disque public vers le disque privé, chiffrées.
 * Idempotente : une photo déjà privée est ignorée. À lancer une fois en ligne après le déploiement.
 */
class MigrerPhotosSignalements extends Command
{
    protected $signature = 'security:migrer-photos';

    protected $description = 'Chiffre et déplace sur le disque privé les photos de signalement encore publiques';

    public function handle(): int
    {
        $migrees = 0;
        $absentes = 0;

        Signalement::query()
            ->whereNotNull('photo')
            ->where('photo', 'not like', OptimiseurImage::PREFIXE_PRIVE.'%')
            ->chunkById(100, function ($signalements) use (&$migrees, &$absentes): void {
                foreach ($signalements as $signalement) {
                    $ancien = (string) $signalement->photo;

                    if (! Storage::disk('public')->exists($ancien)) {
                        $absentes++;

                        continue;
                    }

                    foreach (array_filter([$ancien, OptimiseurImage::miniature($ancien)]) as $chemin) {
                        $contenu = Storage::disk('public')->get($chemin);

                        if ($contenu !== null) {
                            Storage::disk(OptimiseurImage::DISQUE_PRIVE)->put(OptimiseurImage::PREFIXE_PRIVE.$chemin, Crypt::encryptString($contenu));
                        }
                    }

                    $signalement->forceFill(['photo' => OptimiseurImage::PREFIXE_PRIVE.$ancien])->saveQuietly();
                    Storage::disk('public')->delete(array_filter([$ancien, OptimiseurImage::miniature($ancien)]));
                    $migrees++;
                }
            });

        $this->info("Photos chiffrées et déplacées : {$migrees}. Fichiers introuvables (ignorés) : {$absentes}.");

        return self::SUCCESS;
    }
}
