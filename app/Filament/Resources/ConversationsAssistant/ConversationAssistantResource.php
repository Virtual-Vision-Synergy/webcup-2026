<?php

namespace App\Filament\Resources\ConversationsAssistant;

use App\Filament\Resources\ConversationsAssistant\Pages\ListConversationsAssistant;
use App\Models\ConversationAssistant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * F91 : échanges anonymisés avec l'assistant, en lecture seule (ConversationAssistantPolicy : admins uniquement).
 * Les messages « Non compris » indiquent les mots-clés ou règles à ajouter.
 */
class ConversationAssistantResource extends Resource
{
    protected static ?string $model = ConversationAssistant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?string $modelLabel = 'échange avec l’assistant';

    protected static ?string $pluralModelLabel = 'conversations de l’assistant';

    protected static ?string $navigationLabel = 'Conversations de l’assistant';

    protected static ?string $slug = 'conversations-assistant';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('service:id,nom'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('message')
                    ->label('Message (anonymisé)')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Réponse')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ConversationAssistant::TYPE_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ConversationAssistant::TYPE_SERVICE, ConversationAssistant::TYPE_REGLE => 'success',
                        ConversationAssistant::TYPE_PRECISION => 'warning',
                        ConversationAssistant::TYPE_URGENCE, ConversationAssistant::TYPE_REPLI => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('service.nom')
                    ->label('Service proposé')
                    ->placeholder('—'),
                TextColumn::make('conversation')
                    ->label('Conversation')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 8))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Réponse')
                    ->options(ConversationAssistant::TYPE_LABELS),
            ])
            ->emptyStateHeading('Aucun échange')
            ->emptyStateDescription('Les questions posées à l’assistant « Besoin d’aide ? » apparaîtront ici, anonymisées.');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversationsAssistant::route('/'),
        ];
    }
}
