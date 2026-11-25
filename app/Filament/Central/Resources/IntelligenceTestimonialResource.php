<?php

namespace App\Filament\Central\Resources;

use App\Domain\Intelligence\Models\IntelligenceTestimonial;
use App\Filament\Central\Resources\IntelligenceTestimonialResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Central › Depoimentos do Intelligence: o que aparece na página oravel.com.br/intelligence. Só publica se aprovado E autorizado. */
class IntelligenceTestimonialResource extends Resource
{
    protected static ?string $model = IntelligenceTestimonial::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationLabel = 'Depoimentos do Intelligence';

    protected static ?string $navigationGroup = 'Clientes e Contratos';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Depoimento';

    protected static ?string $pluralModelLabel = 'Depoimentos do Intelligence';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return self::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(120),
            Forms\Components\TextInput::make('cargo')->label('Cargo')->maxLength(120),
            Forms\Components\TextInput::make('empresa')->label('Empresa (opcional)')->helperText('Deixe vazio se o cliente preferir não ser identificado pela empresa.')->maxLength(120),
            Forms\Components\Textarea::make('texto')->label('Depoimento')->required()->rows(4)->maxLength(600)->helperText('Texto exatamente como o cliente aprovou. Nada inventado.'),
            Forms\Components\Toggle::make('autorizacao_uso')->label('O cliente autorizou publicar')->live()->default(false),
            Forms\Components\Textarea::make('autorizacao_registro')->label('Como e quando autorizou')->rows(2)
                ->helperText('Ex.: "Autorizou por e-mail em 12/12/2026". Guarde a prova fora do sistema.')
                ->required(fn (Get $get) => (bool) $get('autorizacao_uso')),
            Forms\Components\Toggle::make('aprovado')->label('Aprovado para aparecer na página')->default(false)
                ->disabled(fn (Get $get) => ! $get('autorizacao_uso'))
                ->helperText('Só pode ser aprovado depois de registrar a autorização do cliente.')
                ->dehydrateStateUsing(fn ($state, Get $get) => (bool) $state && (bool) $get('autorizacao_uso')),
            Forms\Components\TextInput::make('ordem')->label('Ordem')->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable()->description(fn (IntelligenceTestimonial $r) => collect([$r->cargo, $r->empresa])->filter()->implode(', ')),
                Tables\Columns\TextColumn::make('texto')->limit(70),
                Tables\Columns\IconColumn::make('autorizacao_uso')->label('Autorizado')->boolean(),
                Tables\Columns\IconColumn::make('aprovado')->label('Aprovado')->boolean(),
                Tables\Columns\TextColumn::make('ordem')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntelligenceTestimonials::route('/'),
            'create' => Pages\CreateIntelligenceTestimonial::route('/create'),
            'edit' => Pages\EditIntelligenceTestimonial::route('/{record}/edit'),
        ];
    }
}
