<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogoGrupoResource\Pages;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\Material;
use App\Services\Catalogo\CatalogoGestao;
use App\Services\Catalogo\CatalogoTemplateService;
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

/**
 * G1 fase 2 -- Grupos de Equipamento (AssetCategory + perfil do catalogo). Acesso pela CatalogoItemPolicy
 * (chave tabela_catalogo_equipamentos, desligada por padrao; permissao editar_catalogo_equipamentos = gerenciar).
 */
class CatalogoGrupoResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static ?string $slug = 'catalogo-grupos';

    protected static ?string $modelLabel = 'Grupo de Equipamento';

    protected static ?string $pluralModelLabel = 'Grupos de Equipamento';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?int $navigationSort = 5;

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
        return false; // nada de exclusao direta: arquivar / excluir definitivo passam por regras (acoes da tabela)
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    private static function tenantId(): ?string
    {
        return auth()->user()?->tenant_id ?? Tenancy::current()?->id;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Perfil')->persistTabInQueryString()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make('Geral')->schema([
                    Forms\Components\TextInput::make('name')->label('Nome do grupo')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    Forms\Components\TextInput::make('slug')->label('Slug (identificador estável)')->required()->maxLength(255)
                        ->disabledOn('edit')->dehydrated()
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', static::tenantId())),
                    Forms\Components\Textarea::make('description')->label('Descrição')->rows(2)->columnSpanFull(),
                    Forms\Components\Select::make('medidor')->label('Medidor')->required()
                        ->options(['horimetro' => 'Horímetro', 'km' => 'Km', 'ambos' => 'Km + horímetro', 'dias' => 'Dias']),
                    Forms\Components\Select::make('criticidade_padrao')->label('Criticidade padrão')->required()
                        ->options(['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa']),
                    Forms\Components\Toggle::make('exige_operador')->label('Exige operador habilitado'),
                ])->columns(2),

                Forms\Components\Tabs\Tab::make('Campos técnicos')->schema([
                    static::repetidor('atributos', 'Campo técnico', 'Campos técnicos (aparecem só no cadastro dos ativos deste grupo)', [
                        Forms\Components\TextInput::make('rotulo')->label('Rótulo')->required(),
                        Forms\Components\TextInput::make('chave')->label('Chave')->required()->helperText('Identificador do campo (sem espaços).'),
                        Forms\Components\Select::make('tipo')->label('Tipo')->required()
                            ->options(['texto' => 'Texto', 'numero' => 'Número', 'selecao' => 'Seleção', 'booleano' => 'Sim/Não', 'data' => 'Data']),
                        Forms\Components\TextInput::make('unidade')->label('Unidade'),
                        Forms\Components\TagsInput::make('opcoes')->label('Opções (para Seleção)'),
                        Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório'),
                    ], fn (array $state) => ($state['rotulo'] ?? 'Novo campo'), fn (array $d) => $d + ['slug' => Str::slug((string) ($d['chave'] ?? uniqid()))]),
                ]),

                Forms\Components\Tabs\Tab::make('Documentos, inspeções e checklist')->schema([
                    Forms\Components\Placeholder::make('aviso_norma')->hiddenLabel()->content('Requisitos marcados com "Depende de norma" devem ser validados com o responsável técnico antes de uso real.'),
                    static::repetidor('requisitos', 'Requisito', 'Requisitos do grupo (itens da biblioteca com o efeito próprio deste grupo)', [
                        Forms\Components\Select::make('item_id')->label('Item da biblioteca')->required()->searchable()
                            ->options(fn () => CatalogoItem::query()->ativos()->orderBy('nome')->pluck('nome', 'id')->all()),
                        Forms\Components\Select::make('efeito')->label('Efeito na aptidão')->required()->options([
                            'IMP' => 'IMP — impeditivo (reprovado = Inapto e gera OS)', 'ALR' => 'ALR — alerta (Apto com restrição)',
                            'DOC' => 'DOC — documento com validade', 'INSP' => 'INSP — inspeção periódica', 'ACESS' => 'ACESS — acessório',
                        ]),
                        Forms\Components\TextInput::make('validade_dias')->label('Validade (dias)')->numeric()->minValue(1),
                        Forms\Components\Toggle::make('gera_os')->label('Reprovado gera OS'),
                        Forms\Components\TextInput::make('norma')->label('Norma'),
                        Forms\Components\Toggle::make('validar_norma')->label('Depende de norma (validar)'),
                    ], fn (array $state) => ($state['efeito'] ?? '?').' · '.(CatalogoItem::withoutGlobalScopes()->find($state['item_id'] ?? null)?->nome ?? 'novo requisito'),
                        fn (array $d) => $d + ['slug' => Str::slug((string) (CatalogoItem::withoutGlobalScopes()->find($d['item_id'] ?? null)?->slug ?? 'req')).'-'.Str::lower(Str::random(4))]),
                ]),

                Forms\Components\Tabs\Tab::make('Preventiva')->schema([
                    static::repetidor('preventivas', 'Plano', 'Planos de preventiva (siga o manual do fabricante)', [
                        Forms\Components\TextInput::make('nome')->label('Nome')->required(),
                        Forms\Components\Select::make('medidor')->label('Medidor')->required()->options(['horimetro' => 'Horímetro', 'km' => 'Km', 'dias' => 'Dias']),
                        Forms\Components\TextInput::make('intervalo')->label('Intervalo')->numeric()->minValue(1)->required(),
                        Forms\Components\TextInput::make('tolerancia_pct')->label('Tolerância (%)')->numeric()->minValue(0)->maxValue(100)->default(10)
                            ->helperText('Até este % do intervalo vencido = Apto com restrição; acima = Inapto.'),
                        Forms\Components\TextInput::make('ou_dias')->label('Ou a cada (dias)')->numeric()->minValue(1),
                        Forms\Components\Select::make('regra_ou')->label('Regra')->options(['primeiro' => 'O que vier primeiro', 'ambos' => 'Ambos']),
                    ], fn (array $state) => ($state['nome'] ?? 'Novo plano'), fn (array $d) => $d + ['slug' => Str::slug((string) ($d['nome'] ?? 'plano')).'-'.Str::lower(Str::random(4))]),
                ]),

                Forms\Components\Tabs\Tab::make('Operador e peças')->schema([
                    static::repetidor('operadores', 'Habilitação', 'Operador exigido', [
                        Forms\Components\TextInput::make('habilitacao')->label('Habilitação')->required(),
                        Forms\Components\TextInput::make('norma')->label('Norma'),
                        Forms\Components\TextInput::make('validade_dias')->label('Validade (dias)')->numeric(),
                        Forms\Components\Toggle::make('obrigatorio')->label('Obrigatória')->default(true),
                        Forms\Components\Toggle::make('validar_norma')->label('Depende de norma (validar)'),
                    ], fn (array $state) => ($state['habilitacao'] ?? 'Nova habilitação'), fn (array $d) => $d + ['slug' => Str::slug((string) ($d['habilitacao'] ?? 'op')).'-'.Str::lower(Str::random(4))]),
                    static::repetidor('pecasCriticas', 'Peça', 'Peças críticas (vinculadas a Materiais — é o que a OS consome)', [
                        Forms\Components\Select::make('material_id')->label('Material')->searchable()
                            ->getSearchResultsUsing(fn (string $q) => Material::query()->where('name', 'ilike', "%{$q}%")->limit(30)->pluck('name', 'id')->all())
                            ->getOptionLabelUsing(fn ($v) => Material::query()->find($v)?->name),
                        Forms\Components\TextInput::make('descricao')->label('Descrição')->required(),
                    ], fn (array $state) => ($state['descricao'] ?? 'Nova peça'), fn (array $d) => $d + ['slug' => Str::slug((string) ($d['descricao'] ?? 'peca')).'-'.Str::lower(Str::random(4))]),
                ]),

                Forms\Components\Tabs\Tab::make('SLA e regras')->schema([
                    static::repetidor('slaPoliticas', 'SLA', 'SLA por criticidade (reaproveita a política da F2 na fase 7)', [
                        Forms\Components\Select::make('criticidade')->label('Criticidade')->required()->options(['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa']),
                        Forms\Components\TextInput::make('resposta_min')->label('Resposta (min)')->numeric()->minValue(1),
                        Forms\Components\TextInput::make('resolucao_min')->label('Resolução (min)')->numeric()->minValue(1),
                        Forms\Components\Select::make('calendario')->label('Calendário')->options(['comercial' => 'Horário comercial', '24x7' => '24x7'])->default('comercial'),
                        Forms\Components\TagsInput::make('pausa_por')->label('Pausa por (ex.: aguardando peça)'),
                    ], fn (array $state) => ucfirst((string) ($state['criticidade'] ?? 'novo SLA')), fn (array $d) => $d + ['slug' => Str::slug((string) ($d['criticidade'] ?? 'sla')).'-'.Str::lower(Str::random(4))]),
                    static::repetidor('regras', 'Regra', 'Regras especiais (condição + efeito sobre a aptidão)', [
                        Forms\Components\TextInput::make('nome')->label('Nome')->required(),
                        Forms\Components\Select::make('efeito')->label('Efeito')->required()->options(['restricao' => 'Apto com restrição', 'inapto' => 'Inapto', 'bloqueia_alocacao' => 'Bloqueia alocação']),
                        Forms\Components\KeyValue::make('condicao')->label('Condição (campo → valor)'),
                        Forms\Components\TextInput::make('strategy')->label('Strategy (código específico)')->disabled()->helperText('Só definida pelo template (ex.: nr13).'),
                        Forms\Components\Toggle::make('validar_norma')->label('Depende de norma (validar)'),
                    ], fn (array $state) => ($state['nome'] ?? 'Nova regra'), fn (array $d) => $d + ['slug' => Str::slug((string) ($d['nome'] ?? 'regra')).'-'.Str::lower(Str::random(4))]),
                ]),
            ]),

            Forms\Components\Section::make('Justificativa')->columnSpanFull()
                ->description('Obrigatória para edições sensíveis: IMP→ALR, remover/desativar documento, inspeção ou item impeditivo, ampliar intervalo de preventiva. Valide com o responsável técnico. Fica registrada no log com antes e depois.')
                ->schema([Forms\Components\Textarea::make('justificativa')->label('Justificativa')->rows(2)->dehydrated(false)])
                ->visibleOn('edit'),
        ]);
    }

    /** Repeater por relacao, com ordem, ativo e metadados do catalogo (origem tenant + personalizado em itens novos). */
    private static function repetidor(string $relacao, string $rotulo, string $titulo, array $campos, \Closure $itemLabel, \Closure $mutarCriacao): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($relacao)->label($titulo)->relationship($relacao)
            ->schema([...$campos, Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true)->helperText('Desligue para arquivar sem apagar.')])
            ->columns(2)->orderColumn('ordem')->collapsible()->collapsed()->itemLabel($itemLabel)->addActionLabel("Adicionar {$rotulo}")
            ->mutateRelationshipDataBeforeCreateUsing(function (array $d) use ($mutarCriacao): array {
                return $mutarCriacao($d) + ['tenant_id' => static::tenantId(), 'origem' => 'tenant', 'personalizado' => true, 'ativo' => $d['ativo'] ?? true];
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('requisitos'))
            ->defaultSort('ordem')
            ->reorderable('ordem', CatalogoAcesso::podeGerenciar())
            ->recordUrl(fn (AssetCategory $r) => CatalogoAcesso::podeGerenciar() && ! $r->trashed()
                ? static::getUrl('edit', ['record' => $r]) : static::getUrl('view', ['record' => $r]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Grupo')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('medidor')->label('Medidor')->badge(),
                Tables\Columns\TextColumn::make('criticidade_padrao')->label('Criticidade')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'critica' => 'danger', 'alta' => 'warning', 'media' => 'info', default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('exige_operador')->label('Operador')->boolean(),
                Tables\Columns\TextColumn::make('requisitos_count')->label('Requisitos'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge()
                    ->color(fn (?string $state) => $state === 'sistema' ? 'gray' : 'primary'),
                Tables\Columns\IconColumn::make('personalizado')->label('Personalizado')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->filters([Tables\Filters\TrashedFilter::make()->label('Arquivados')])
            ->actions([
                Tables\Actions\Action::make('clonar')->label('Clonar')->icon('heroicon-o-document-duplicate')
                    ->visible(fn (AssetCategory $r) => CatalogoAcesso::podeGerenciar() && ! $r->trashed())
                    ->form([Forms\Components\TextInput::make('nome')->label('Nome do novo grupo')->required()->maxLength(255)])
                    ->action(function (AssetCategory $record, array $data) {
                        $novo = app(CatalogoGestao::class)->clonarGrupo($record, $data['nome']);
                        Notification::make()->success()->title("Grupo \"{$novo->name}\" criado a partir de \"{$record->name}\".")->send();
                    }),
                Tables\Actions\Action::make('arquivar')->label('Arquivar')->icon('heroicon-o-archive-box')->color('warning')
                    ->visible(fn (AssetCategory $r) => CatalogoAcesso::podeGerenciar() && ! $r->trashed())
                    ->modalDescription(fn (AssetCategory $r) => app(CatalogoGestao::class)->ativosNoGrupo($r) > 0
                        ? 'Este grupo tem ativos. Escolha outro grupo para migrá-los.' : 'O grupo some das listas, mas o histórico é preservado.')
                    ->form(fn (AssetCategory $r) => [
                        Forms\Components\Select::make('migrar_para')->label('Migrar os ativos para')
                            ->visible(app(CatalogoGestao::class)->ativosNoGrupo($r) > 0)->required(app(CatalogoGestao::class)->ativosNoGrupo($r) > 0)
                            ->options(fn () => AssetCategory::query()->where('is_active', true)->whereKeyNot($r->id)->orderBy('name')->pluck('name', 'id')->all()),
                        Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required()->minLength(5),
                    ])
                    ->action(function (AssetCategory $record, array $data) {
                        try {
                            app(CatalogoGestao::class)->arquivarGrupo($record, $data['migrar_para'] ?? null, $data['justificativa']);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Não foi possível arquivar')->body(collect($e->errors())->flatten()->first())->send();

                            return;
                        }
                        Notification::make()->success()->title('Grupo arquivado.')->send();
                    }),
                Tables\Actions\Action::make('restaurar_padrao')->label('Restaurar padrão')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (AssetCategory $r) => CatalogoAcesso::podeGerenciar() && ! $r->trashed() && filled($r->slug_template))
                    ->modalDescription('Volta o perfil deste grupo e todos os itens que vieram do padrão (inclusive os que você arquivou ou personalizou) para a versão do padrão. Itens que você criou ficam como estão. Fica registrado em log.')
                    ->form([Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required()->minLength(5)->rows(2)])
                    ->action(function (AssetCategory $record, array $data) {
                        try {
                            $r = app(CatalogoTemplateService::class)->restaurarGrupo(Tenancy::current(), $record, $data['justificativa'], auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Não foi possível restaurar')->body(collect($e->errors())->flatten()->first())->persistent()->send();

                            return;
                        }
                        Notification::make()->success()->title($r['aplicadas'] ? "{$r['aplicadas']} item(ns) restaurado(s) ao padrão." : 'O grupo já está igual ao padrão.')->send();
                    }),
                Tables\Actions\Action::make('restaurar')->label('Restaurar')->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (AssetCategory $r) => CatalogoAcesso::podeGerenciar() && $r->trashed())
                    ->action(function (AssetCategory $record) {
                        app(CatalogoGestao::class)->restaurarGrupo($record);
                        Notification::make()->success()->title('Grupo restaurado.')->send();
                    }),
                Tables\Actions\Action::make('purgar')->label('Excluir definitivamente')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn (AssetCategory $r) => CatalogoAcesso::podeGerenciar() && ! app(CatalogoGestao::class)->grupoTemHistorico($r))
                    ->requiresConfirmation()->modalDescription('Este grupo nunca foi usado por nenhum ativo. A exclusão é definitiva e apaga o perfil dele.')
                    ->action(function (AssetCategory $record) {
                        app(CatalogoGestao::class)->purgarGrupo($record);
                        Notification::make()->success()->title('Grupo excluído.')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogoGrupos::route('/'),
            'create' => Pages\CreateCatalogoGrupo::route('/create'),
            'view' => Pages\ViewCatalogoGrupo::route('/{record}'),
            'edit' => Pages\EditCatalogoGrupo::route('/{record}/edit'),
        ];
    }
}
