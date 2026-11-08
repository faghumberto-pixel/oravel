<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogoItemResource\Pages;
use App\Models\CatalogoItem;
use App\Services\Catalogo\CatalogoGestao;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

/** G1 fase 2 -- Biblioteca de itens reutilizaveis (Extintor, Freios, CRLV...). Mesmo acesso do catalogo (CatalogoItemPolicy). */
class CatalogoItemResource extends Resource
{
    protected static ?string $model = CatalogoItem::class;

    protected static ?string $slug = 'catalogo-itens';

    protected static ?string $modelLabel = 'Item da Biblioteca';

    protected static ?string $pluralModelLabel = 'Biblioteca de Itens';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public static function canViewAny(): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public static function canView(Model $record): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public static function canCreate(): bool
    {
        return CatalogoAcesso::podeGerenciar();
    }

    public static function canEdit(Model $record): bool
    {
        return CatalogoAcesso::podeGerenciar() && ! $record->trashed();
    }

    public static function canDelete(Model $record): bool
    {
        return false; // arquivar / excluir definitivo passam por regras (acoes da tabela)
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        $tenantId = fn () => auth()->user()?->tenant_id ?? Tenancy::current()?->id;

        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(255)->live(onBlur: true)
                ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            Forms\Components\TextInput::make('slug')->label('Slug (identificador estável)')->required()->maxLength(255)->disabledOn('edit')->dehydrated()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', $tenantId())),
            Forms\Components\Select::make('tipo')->label('Tipo')->required()
                ->options(['checklist' => 'Checklist', 'documento' => 'Documento', 'inspecao' => 'Inspeção', 'acessorio' => 'Acessório', 'operador' => 'Operador']),
            Forms\Components\TextInput::make('validade_dias')->label('Validade padrão (dias)')->numeric()->minValue(1),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(2)->columnSpanFull(),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true)
                ->helperText('Desativar um item usado como impeditivo (IMP/DOC/INSP) exige justificativa.'),
            Forms\Components\Textarea::make('justificativa')->label('Justificativa (edições sensíveis)')->rows(2)->dehydrated(false)->visibleOn('edit')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('requisitos'))
            ->defaultSort('nome')
            ->recordUrl(fn (CatalogoItem $r) => CatalogoAcesso::podeGerenciar() && ! $r->trashed()
                ? static::getUrl('edit', ['record' => $r]) : static::getUrl('view', ['record' => $r]))
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Item')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('requisitos_count')->label('Usado em (grupos)'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge()->color(fn (?string $state) => $state === 'sistema' ? 'gray' : 'primary'),
                Tables\Columns\IconColumn::make('personalizado')->label('Personalizado')->boolean(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->options(['checklist' => 'Checklist', 'documento' => 'Documento', 'inspecao' => 'Inspeção', 'acessorio' => 'Acessório', 'operador' => 'Operador']),
                Tables\Filters\TrashedFilter::make()->label('Arquivados'),
            ])
            ->actions([
                Tables\Actions\Action::make('remover')->label(fn (CatalogoItem $r) => app(CatalogoGestao::class)->itemTemHistorico($r) ? 'Arquivar' : 'Excluir')
                    ->icon('heroicon-o-archive-box')->color('danger')
                    ->visible(fn (CatalogoItem $r) => CatalogoAcesso::podeGerenciar() && ! $r->trashed())
                    ->requiresConfirmation()
                    ->modalDescription(fn (CatalogoItem $r) => app(CatalogoGestao::class)->itemTemHistorico($r)
                        ? 'Este item já é usado por grupos: será apenas arquivado (o histórico é preservado).'
                        : 'Este item nunca foi usado: a exclusão é definitiva.')
                    ->form(fn (CatalogoItem $r) => app(CatalogoGestao::class)->itemUsadoEmImpeditivo($r)
                        ? [Forms\Components\Textarea::make('justificativa')->label('Justificativa (item impeditivo em uso — validar com o responsável técnico)')->required()->minLength(5)]
                        : [])
                    ->action(function (CatalogoItem $record, array $data) {
                        try {
                            $resultado = app(CatalogoGestao::class)->removerItem($record, $data['justificativa'] ?? null);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Não foi possível remover')->body(collect($e->errors())->flatten()->first())->send();

                            return;
                        }
                        Notification::make()->success()->title("Item {$resultado}.")->send();
                    }),
                Tables\Actions\Action::make('restaurar')->label('Restaurar')->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (CatalogoItem $r) => CatalogoAcesso::podeGerenciar() && $r->trashed())
                    ->action(function (CatalogoItem $record) {
                        $record->restore();
                        $record->forceFill(['ativo' => true])->save();
                        app(CatalogoGestao::class)->log($record, 'Catalogo: item da biblioteca restaurado');
                        Notification::make()->success()->title('Item restaurado.')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogoItens::route('/'),
            'create' => Pages\CreateCatalogoItem::route('/create'),
            'view' => Pages\ViewCatalogoItem::route('/{record}'),
            'edit' => Pages\EditCatalogoItem::route('/{record}/edit'),
        ];
    }
}
