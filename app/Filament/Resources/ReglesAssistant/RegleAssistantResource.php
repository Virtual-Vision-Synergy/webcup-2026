<?php

namespace App\Filament\Resources\ReglesAssistant;

use App\Filament\Resources\ReglesAssistant\Pages\ManageReglesAssistant;
use App\Models\RegleAssistant;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * F91 : règles de l'assistant d'orientation (questions fréquentes → réponse + lien interne).
 * Réservé aux admins (RegleAssistantPolicy) ; prises en compte immédiatement par la bulle « Besoin d'aide ? ».
 */
class RegleAssistantResource extends Resource
{
    protected static ?string $model = RegleAssistant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'règle de l’assistant';

    protected static ?string $pluralModelLabel = 'règles de l’assistant';

    protected static ?string $navigationLabel = 'Règles de l’assistant';

    protected static ?string $recordTitleAttribute = 'declencheurs';

    protected static ?string $slug = 'regles-assistant';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('declencheurs')
                    ->label('Expressions de l’habitant')
                    ->helperText('Séparées par des virgules : « mot de passe, oublié, compte bloqué ». Fautes légères, accents et majuscules tolérés.')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                Textarea::make('reponse')
                    ->label('Réponse de l’assistant')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('lien_libelle')
                    ->label('Texte du lien')
                    ->maxLength(100)
                    ->requiredWith('lien_url'),
                TextInput::make('lien_url')
                    ->label('Lien (page du site)')
                    ->helperText('Chemin interne commençant par « / », par exemple /rendez-vous/prendre.')
                    ->maxLength(255)
                    ->requiredWith('lien_libelle')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! RegleAssistant::lienInterneValide((string) $value)) {
                            $fail('Le lien doit être une page du site, commençant par « / ».');
                        }
                    }),
                Toggle::make('actif')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('declencheurs')
                    ->label('Expressions')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('lien_libelle')
                    ->label('Lien proposé')
                    ->placeholder('—'),
                IconColumn::make('actif')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Modifiée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Aucune règle')
            ->emptyStateDescription('Ajoutez les questions fréquentes des habitants et la page vers laquelle les orienter.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReglesAssistant::route('/'),
        ];
    }
}
