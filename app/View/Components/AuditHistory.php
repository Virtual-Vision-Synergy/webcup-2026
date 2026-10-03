<?php

namespace App\View\Components;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Historique des modifications d'une fiche (F48), lu dans le journal d'audit (F47).
 *
 * Section complète : <x-audit-history :subject="$record" />
 * Résumé en haut de fiche (« Dernière modification par… » + bouton « Historique (n) ») : <x-audit-history :subject="$record" variant="resume" />
 *
 * Rien n'est rendu si l'utilisateur ne peut pas lire le journal ET ouvrir l'élément (AuditLog::peutVoirHistorique).
 */
class AuditHistory extends Component
{
    public const VARIANTS = ['complet', 'resume'];

    /** Nombre d'entrées affichées sur la fiche (le reste sur la page « Voir tout l'historique »). */
    public const LIMITE = 10;

    /** @var Collection<int, AuditLog> */
    public Collection $entrees;

    public int $total = 0;

    public function __construct(
        public Model $subject,
        public string $variant = 'complet',
    ) {
        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException("Variante d'historique inconnue : {$variant}.");
        }

        $this->entrees = new Collection;
    }

    public function shouldRender(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! AuditLog::peutVoirHistorique($user, $this->subject)) {
            return false;
        }

        $requete = AuditLog::query()
            ->where('subject_type', class_basename($this->subject))
            ->where('subject_id', $this->subject->getKey());

        $this->total = (clone $requete)->count();
        $this->entrees = $requete->latest('id')->limit($this->variant === 'resume' ? 1 : self::LIMITE)->get();

        return true;
    }

    public function lienComplet(): string
    {
        return route('agent.history.show', ['type' => AuditLog::slugFor($this->subject), 'id' => $this->subject->getKey()]);
    }

    public function render(): View
    {
        return view('components.audit-history');
    }
}
