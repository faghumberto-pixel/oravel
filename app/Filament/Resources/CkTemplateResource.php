<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CkTemplateResource\Pages;
use App\Models\AssetCategory;
use App\Models\CatalogoRequisito;
use App\Models\ChecklistGroup;
use App\Models\CkItem;
use App\Models\CkTemplate;
use App\Services\Checklists\ChecklistGestao;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Checklists por grupo de equipamento (C2): lista, criacao e edicao com seções e itens reordenáveis, duplicar, restaurar
 * padrão e ativar/desativar. Acesso pelo CkTemplatePolicy (AbstractPolicy): trava comercial (chave tabela_ck_templates, desligada
 * por padrão) + permissão individual (ler/criar/editar/excluir_checklist_equipamento; admin do tenant tem bypass). Quem pode
 * editar é decidido pelas permissões dos perfis, não por papel fixo no código.
 */
class CkTemplateResource extends BaseResource
{
    protected static ?string $model = CkTemplate::class;

    protected static ?string $slug = 'checklists-equipamento';

    protected static ?string $modelLabel = 'Checklist de Equipamento';

    protected static ?string $pluralModelLabel = 'Checklists de Equipamento';

    protected static ?string $navigationLabel = 'Checklists de equipamento';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?int $navigationSort = 9;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && Gate::allows('viewAny', CkTemplate::class);
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view', $record);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && Gate::allows('create', CkTemplate::class);
    }

    public static function canEdit(Model $record): bool
    {
        return ! $record->trashed() && Gate::allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->trashed() && Gate::allows('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return $record->trashed() && Gate::allows('update', $record);
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function tenantId(): ?string
    {
        return auth()->user()?->tenant_id ?? Tenancy::current()?->id;
    }

    /** Grupos de equipamento do catálogo (G1) da empresa. */
    private static function opcoesGrupos(): array
    {
        return AssetCategory::query()->where('tenant_id', static::tenantId())->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Grupos de checklist da empresa: o grupo do ativo (campo "Grupo de checklist") e o que ativa o checklist na OS. */
    private static function opcoesGruposChecklist(): array
    {
        return ChecklistGroup::query()->where('tenant_id', static::tenantId())->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Requisitos de checklist do grupo do template (o item crítico reprovado alimenta o requisito escolhido). */
    private static function opcoesRequisitos($livewire): array
    {
        $grupoId = $livewire->data['grupo_id'] ?? $livewire->getRecord()?->grupo_id;

        if (! $grupoId) {
            return [];
        }

        return CatalogoRequisito::query()->with(['item' => fn ($q) => $q->withoutGlobalScopes()])->where('tenant_id', static::tenantId())
            ->where('grupo_id', $grupoId)->whereIn('efeito', ['IMP', 'ALR'])->where('ativo', true)->orderBy('ordem')->get()
            ->mapWithKeys(fn (CatalogoRequisito $r) => [$r->id => ($r->item?->nome ?? $r->slug).' ('.$r->efeito.')'])->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do checklist')->schema([
                Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\Select::make('grupo_id')->label('Grupo de equipamento')->options(fn () => static::opcoesGrupos())->searchable()->live()
                    ->helperText('Define em quais ativos o checklist se aplica. Vazio = checklist avulso.'),
                Forms\Components\Select::make('checklist_group_id')->label('Grupo de checklist (ativa na OS)')->options(fn () => static::opcoesGruposChecklist())->searchable()
                    ->placeholder('Nenhum')
                    ->helperText('Vale para os ativos que têm este grupo de checklist (campo "Grupo de checklist" do ativo). Sem grupo de checklist, vale para todos os ativos do grupo de equipamento.'),
                Forms\Components\Select::make('tipo')->label('Tipo')->required()->options(CkTemplate::TIPOS),
                Forms\Components\Select::make('periodicidade')->label('Periodicidade')->required()->options(CkTemplate::PERIODICIDADES)->default('por_uso')->live(),
                Forms\Components\TextInput::make('intervalo_horimetro_km')->label('A cada (horímetro ou km)')->numeric()->minValue(1)
                    ->visible(fn (Forms\Get $get) => $get('periodicidade') === 'por_medidor'),
                Forms\Components\TextInput::make('referencia_manual')->label('Referência (norma ou manual)')->maxLength(255)
                    ->helperText('Texto livre. Normas são referência e precisam ser validadas com o responsável técnico.')->columnSpanFull(),
                Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
                Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
            ])->columns(3),

            Forms\Components\Section::make('Seções e itens')->description('Arraste para reordenar. Remover um item ou seção não apaga execuções já feitas: elas guardam o texto da época.')->schema([
                Forms\Components\Repeater::make('secoes')->label('Seções')->relationship('secoes')->orderColumn('ordem')->collapsible()->cloneable(false)
                    ->itemLabel(fn (array $state) => $state['titulo'] ?? 'Nova seção')->addActionLabel('Adicionar seção')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => $data + ['tenant_id' => static::tenantId()])
                    ->schema([
                        Forms\Components\TextInput::make('titulo')->label('Título da seção')->required()->maxLength(255),
                        Forms\Components\Repeater::make('itens')->label('Itens')->relationship('itens')->orderColumn('ordem')->collapsible()->collapsed()->cloneable()
                            ->itemLabel(fn (array $state) => Str::limit((string) ($state['descricao'] ?? 'Novo item'), 80).(! empty($state['critico']) ? '  ·  CRÍTICO' : ''))
                            ->addActionLabel('Adicionar item')
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => $data + ['tenant_id' => static::tenantId(), 'personalizado' => true])
                            ->schema([
                                Forms\Components\Textarea::make('descricao')->label('O que conferir')->required()->rows(2)->columnSpanFull(),
                                Forms\Components\Select::make('tipo_resposta')->label('Tipo de resposta')->required()->options(CkItem::TIPOS_RESPOSTA)->default('conforme_nao_conforme_na')->live(),
                                Forms\Components\TextInput::make('unidade')->label('Unidade')->maxLength(20)->visible(fn (Forms\Get $get) => $get('tipo_resposta') === 'numerico')->requiredIf('tipo_resposta', 'numerico'),
                                Forms\Components\TagsInput::make('opcoes')->label('Opções')->visible(fn (Forms\Get $get) => $get('tipo_resposta') === 'selecao')->requiredIf('tipo_resposta', 'selecao'),
                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório')->default(true),
                                Forms\Components\Toggle::make('critico')->label('Crítico')->default(false)
                                    ->helperText('Item crítico não conforme torna o ativo inapto (regra aplicada na execução).'),
                                Forms\Components\Toggle::make('exige_foto_se_nao_conforme')->label('Exige foto se não conforme')->default(false),
                                Forms\Components\Toggle::make('validar_norma')->label('Depende de norma (validar)')->default(false),
                                Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true)->helperText('Desligue para esconder da execução sem apagar.'),
                                Forms\Components\TextInput::make('referencia_manual')->label('Referência (norma ou capítulo do manual)')->maxLength(255)->columnSpanFull(),
                                Forms\Components\Select::make('catalogo_requisito_id')->label('Requisito do catálogo que este item alimenta')
                                    ->options(fn ($livewire) => static::opcoesRequisitos($livewire))->searchable()->placeholder('Nenhum')
                                    ->helperText('Escolha o requisito do grupo (aba do catálogo). Item crítico reprovado grava o cumprimento nele.')->columnSpanFull(),
                            ])->columns(2),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['grupo', 'grupoChecklist'])->withCount(['secoes', 'itens']))
            ->defaultSort('nome')
            ->recordUrl(fn (CkTemplate $r) => static::canEdit($r) ? static::getUrl('edit', ['record' => $r]) : null)
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Checklist')->searchable()->sortable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('grupo.name')->label('Grupo')->placeholder('Avulso')->sortable(),
                Tables\Columns\TextColumn::make('grupoChecklist.name')->label('Grupo de checklist')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (?string $state) => CkTemplate::TIPOS[$state] ?? $state),
                Tables\Columns\TextColumn::make('periodicidade')->label('Periodicidade')->formatStateUsing(fn (?string $state) => CkTemplate::PERIODICIDADES[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('itens_count')->label('Itens')->alignEnd(),
                Tables\Columns\TextColumn::make('versao')->label('Versão')->alignEnd()->prefix('v'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === CkTemplate::ORIGEM_PADRAO ? 'Padrão' : 'Customizado')
                    ->color(fn (?string $state) => $state === CkTemplate::ORIGEM_PADRAO ? 'gray' : 'primary'),
                Tables\Columns\IconColumn::make('personalizado')->label('Editado')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('grupo_id')->label('Grupo')->options(fn () => static::opcoesGrupos()),
                Tables\Filters\SelectFilter::make('checklist_group_id')->label('Grupo de checklist')->options(fn () => static::opcoesGruposChecklist()),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(CkTemplate::TIPOS),
                Tables\Filters\SelectFilter::make('origem')->label('Origem')->options([CkTemplate::ORIGEM_PADRAO => 'Padrão', CkTemplate::ORIGEM_CUSTOMIZADO => 'Customizado']),
                Tables\Filters\TernaryFilter::make('ativo')->label('Ativo'),
                Tables\Filters\TrashedFilter::make()->label('Arquivados'),
            ])
            ->actions([
                // Sem EditAction do Filament: ele monta o formulario aninhado (repeater com relationship) sem registro na lista.
                Tables\Actions\Action::make('editar')->label('Editar')->icon('heroicon-o-pencil-square')
                    ->visible(fn (CkTemplate $r) => static::canEdit($r))
                    ->url(fn (CkTemplate $r) => static::getUrl('edit', ['record' => $r])),
                Tables\Actions\Action::make('duplicar')->label('Duplicar')->icon('heroicon-o-document-duplicate')
                    ->visible(fn (CkTemplate $r) => static::canCreate() && ! $r->trashed())
                    ->form([Forms\Components\TextInput::make('nome')->label('Nome do novo checklist')->required()->maxLength(255)])
                    ->fillForm(fn (CkTemplate $r) => ['nome' => $r->nome.' (cópia)'])
                    ->action(function (CkTemplate $record, array $data) {
                        $copia = app(ChecklistGestao::class)->duplicar($record, $data['nome']);
                        Notification::make()->success()->title('Checklist duplicado')->body("Criado “{$copia->nome}”, independente do original.")->send();
                    }),
                Tables\Actions\Action::make('restaurarPadrao')->label('Restaurar padrão')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (CkTemplate $r) => static::canEdit($r) && app(ChecklistGestao::class)->podeRestaurar($r))
                    ->requiresConfirmation()->modalHeading('Restaurar o padrão deste checklist?')
                    ->modalDescription('Substitui TODAS as seções e itens pelo padrão da Oravel e descarta o que a empresa editou neste checklist. Execuções já feitas não mudam. A ação fica registrada com a justificativa.')
                    ->form([Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required()->minLength(5)->rows(2)])
                    ->action(function (CkTemplate $record, array $data) {
                        $r = app(ChecklistGestao::class)->restaurarPadrao($record, $data['justificativa'], auth()->user());
                        Notification::make()->success()->title('Padrão restaurado')->body("“{$r->nome}” voltou ao padrão (versão {$r->versao}).")->send();
                    }),
                Tables\Actions\Action::make('alternarAtivo')->label(fn (CkTemplate $r) => $r->ativo ? 'Desativar' : 'Ativar')
                    ->icon(fn (CkTemplate $r) => $r->ativo ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')->color('gray')
                    ->visible(fn (CkTemplate $r) => static::canEdit($r))
                    ->action(function (CkTemplate $record) {
                        $ativo = app(ChecklistGestao::class)->alternarAtivo($record);
                        Notification::make()->success()->title($ativo ? 'Checklist ativado' : 'Checklist desativado')->send();
                    }),
                Tables\Actions\DeleteAction::make()->label('Arquivar')->modalHeading('Arquivar este checklist?')
                    ->modalDescription('Ele sai das listas de execução. As execuções já feitas continuam no histórico, e você pode restaurá-lo depois.'),
                // Acao propria: o RestoreAction do Filament consulta a ability 'restore' da policy, que o AbstractPolicy nao define.
                Tables\Actions\Action::make('restaurar')->label('Desarquivar')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (CkTemplate $r) => static::canRestore($r))
                    ->action(function (CkTemplate $record) {
                        $record->restore();
                        Notification::make()->success()->title('Checklist desarquivado')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCkTemplates::route('/'),
            'create' => Pages\CreateCkTemplate::route('/create'),
            'edit' => Pages\EditCkTemplate::route('/{record}/edit'),
        ];
    }
}
