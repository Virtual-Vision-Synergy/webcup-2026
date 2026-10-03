<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\SecurityChecker;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * F69 : récapitulatif de la sécurité (docs/SECURITY.md) et résultat de « security:check ».
 * Jamais publique : elle décrit l'architecture de défense. Administrateurs uniquement.
 */
class Securite extends Page
{
    protected static ?string $slug = 'securite';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static ?string $navigationLabel = 'Récapitulatif';

    protected static ?string $title = 'Sécurité de la plateforme';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.securite';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewSecurity', AuditLog::class) ?? false;
    }

    public function mount(): void
    {
        Gate::authorize('viewSecurity', AuditLog::class);
    }

    /**
     * @return list<array{libelle: string, ok: bool, detail: string}>
     */
    public function verifications(): array
    {
        Gate::authorize('viewSecurity', AuditLog::class);

        return app(SecurityChecker::class)->verifier();
    }

    /**
     * Récapitulatif rédigé dans le dépôt (fichier de confiance, HTML brut retiré par sécurité).
     */
    public function recapitulatif(): string
    {
        Gate::authorize('viewSecurity', AuditLog::class);

        $fichier = base_path('docs/SECURITY.md');

        return is_file($fichier)
            ? Str::markdown((string) file_get_contents($fichier), ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : '<p>Récapitulatif introuvable (docs/SECURITY.md).</p>';
    }
}
