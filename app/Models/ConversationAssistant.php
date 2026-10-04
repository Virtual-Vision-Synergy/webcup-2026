<?php

namespace App\Models;

use Database\Factories\ConversationAssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * F91 : un échange anonymisé avec l'assistant d'orientation (aucun utilisateur ni IP, e-mails et numéros masqués).
 * Consulté uniquement par les admins (Filament, ConversationAssistantPolicy) pour améliorer mots-clés et règles.
 *
 * @property int $id
 * @property string $conversation
 * @property string $message
 * @property string $type
 * @property int|null $service_id
 * @property-read Service|null $service
 */
#[Fillable(['conversation', 'message', 'type', 'service_id'])]
class ConversationAssistant extends Model
{
    /** @use HasFactory<ConversationAssistantFactory> */
    use HasFactory;

    protected $table = 'conversations_assistant';

    public const TYPE_SERVICE = 'service';

    public const TYPE_REGLE = 'regle';

    public const TYPE_PRECISION = 'precision';

    public const TYPE_URGENCE = 'urgence';

    public const TYPE_REPLI = 'repli';

    public const TYPE_LABELS = [
        self::TYPE_SERVICE => 'Service trouvé',
        self::TYPE_REGLE => 'Règle',
        self::TYPE_PRECISION => 'Question de précision',
        self::TYPE_URGENCE => 'Urgence',
        self::TYPE_REPLI => 'Non compris (repli)',
    ];

    /**
     * Retire d'un message ce qui pourrait identifier l'habitant : e-mails, numéros (téléphone, dossier…), excès de longueur.
     */
    public static function anonymiser(string $message): string
    {
        $texte = (string) preg_replace('/\S+@\S+/u', '[e-mail]', $message);
        $texte = (string) preg_replace('/\+?\d[\d\s.\-]{3,}\d/u', '[numéro]', $texte);

        return Str::limit(trim($texte), 290);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
