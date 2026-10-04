<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Allège les images envoyées (F60) : redimensionnement à 1600 px maximum, conversion en WebP
 * compressé et miniature de 640 px pour les petits écrans (attribut srcset).
 *
 * Les dimensions sont inscrites dans le nom du fichier (« abc_1600x1200.webp ») : la vue peut
 * renseigner width/height et srcset sans relire le fichier. Si GD ne peut pas traiter l'image,
 * le fichier d'origine est stocké tel quel (aucun envoi n'est perdu).
 */
class OptimiseurImage
{
    public const COTE_MAX = 1600;

    public const LARGEUR_MINIATURE = 640;

    public const QUALITE = 78;

    /** Au-delà, l'image décompressée dépasserait la mémoire disponible sur le serveur mutualisé. */
    public const PIXELS_MAX = 16_000_000;

    public function enregistrer(UploadedFile $fichier, string $dossier, string $disque = 'public'): string
    {
        try {
            $chemin = $this->optimiser($fichier, $dossier, $disque);
        } catch (Throwable $e) {
            report($e);
            $chemin = null;
        }

        return $chemin ?? (string) $fichier->store($dossier, $disque);
    }

    /**
     * Supprime l'image et sa miniature éventuelle.
     */
    public function supprimer(?string $chemin, string $disque = 'public'): void
    {
        if (blank($chemin)) {
            return;
        }

        Storage::disk($disque)->delete(array_filter([$chemin, self::miniature($chemin)]));
    }

    /**
     * Dimensions lues dans le nom du fichier, null pour une image non optimisée.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function dimensions(string $chemin): ?array
    {
        if (! preg_match('/_(\d+)x(\d+)\.webp$/', $chemin, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * Chemin de la miniature 640 px, null si l'image est déjà assez petite (ou non optimisée).
     */
    public static function miniature(string $chemin): ?string
    {
        $dimensions = self::dimensions($chemin);

        if ($dimensions === null || $dimensions[0] <= self::LARGEUR_MINIATURE) {
            return null;
        }

        return Str::replaceLast('.webp', '_m.webp', $chemin);
    }

    private function optimiser(UploadedFile $fichier, string $dossier, string $disque): ?string
    {
        $fichierTemporaire = $fichier->getRealPath();
        $infos = $fichierTemporaire === false ? false : @getimagesize($fichierTemporaire);

        if ($fichierTemporaire === false || ! function_exists('imagewebp') || $infos === false || $infos[0] * $infos[1] > self::PIXELS_MAX) {
            return null;
        }

        $contenu = file_get_contents($fichierTemporaire);
        $source = $contenu === false ? false : @imagecreatefromstring($contenu);

        if ($source === false) {
            return null;
        }

        $source = $this->redresser($source, $fichierTemporaire, $infos[2]);
        $image = $this->redimensionner($source, self::COTE_MAX);
        $largeur = imagesx($image);
        $hauteur = imagesy($image);

        $chemin = trim($dossier, '/').'/'.Str::random(40)."_{$largeur}x{$hauteur}.webp";
        Storage::disk($disque)->put($chemin, $this->encoder($image));

        if ($largeur > self::LARGEUR_MINIATURE) {
            $miniature = $this->redimensionner($image, self::LARGEUR_MINIATURE, largeurSeulement: true);
            Storage::disk($disque)->put((string) self::miniature($chemin), $this->encoder($miniature));
        }

        return $chemin;
    }

    /**
     * Applique l'orientation EXIF des photos de téléphone (sinon elles s'affichent couchées).
     */
    private function redresser(GdImage $image, string $fichierTemporaire, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($fichierTemporaire);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $tournee = imagerotate($image, $angle, 0);

        return $tournee === false ? $image : $tournee;
    }

    private function redimensionner(GdImage $image, int $max, bool $largeurSeulement = false): GdImage
    {
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $ratio = $largeurSeulement ? $max / $largeur : $max / max($largeur, $hauteur);

        if ($ratio >= 1) {
            return $image;
        }

        $nouvelle = imagecreatetruecolor(max(1, (int) round($largeur * $ratio)), max(1, (int) round($hauteur * $ratio)));

        if ($nouvelle === false) {
            return $image;
        }

        imagealphablending($nouvelle, false);
        imagesavealpha($nouvelle, true);
        imagecopyresampled($nouvelle, $image, 0, 0, 0, 0, imagesx($nouvelle), imagesy($nouvelle), $largeur, $hauteur);

        return $nouvelle;
    }

    private function encoder(GdImage $image): string
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITE);

        return (string) ob_get_clean();
    }
}
