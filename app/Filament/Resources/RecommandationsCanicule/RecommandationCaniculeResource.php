<?php

namespace App\Filament\Resources\RecommandationsCanicule;

use App\Filament\Resources\RecommandationsCanicule\Pages\ManageRecommandationsCanicule;
use App\Models\AlerteCanicule;
use App\Models\RecommandationCanicule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * F31 : conseils canicule écrits, par profil × niveau (sans IA). L'admin les modifie ; ils s'affichent aussitôt
 * sur la page « Canicule ». Ni création ni suppression : les 18 couples profil / niveau sont fixes.
 */
class RecommandationCaniculeResource extends Resource
{
    protected static ?string $model = RecommandationCanicule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $modelLabel = 'conseil canicule';

    protected static ?string $pluralModelLabel = 'conseils canicule';

    protected static ?string $navigationLabel = 'Conseils canicule';

    protected static ?string $slug = 'conseils-canicule';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('conseils')
                    ->label('Conseils (un par ligne)')
                    ->helperText('Phrases courtes et concrètes, en langage simple. Une ligne = un conseil.')
                    ->required()
                    ->maxLength(3000)
                    ->rows(8)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('profil')
                    ->label('Profil')
                    ->formatStateUsing(fn (string $state): string => AlerteCanicule::PROFIL_LABELS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AlerteCanicule::NIVEAU_LABELS[$state] ?? $state),
                TextColumn::make('conseils')
                    ->label('Conseils')
                    ->formatStateUsing(fn (string $state): string => count(RecommandationCanicule::lignes($state)).' conseil(s) — '.Str::limit(RecommandationCanicule::lignes($state)[0] ?? '', 70))
                    ->wrap(),
            ])
            ->defaultSort('profil')
            ->filters([
                SelectFilter::make('profil')->label('Profil')->options(AlerteCanicule::PROFIL_LABELS),
                SelectFilter::make('niveau')->label('Niveau')->options(AlerteCanicule::NIVEAU_LABELS),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRecommandationsCanicule::route('/'),
        ];
    }
}
