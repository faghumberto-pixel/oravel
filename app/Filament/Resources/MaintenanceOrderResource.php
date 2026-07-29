<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\MaintenanceOrderResource\Pages;
use App\Filament\Resources\MaintenanceOrderResource\RelationManagers;
use App\Forms\Components\CameraCapture;
use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CriticalityLevel;
use App\Models\EquipmentDamage;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\SolicitacaoLocacao;
use App\Models\User;
use App\Support\FormHelpers;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

class MaintenanceOrderResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = MaintenanceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?string $navigationLabel = 'Ordens de Serviço';

    protected static ?string $pluralModelLabel = 'Ordens de Serviço';

    public static function getNavigationBadge(): ?string
    {
        return 'G O';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->can('update', $record);
    }

    /**
     * Sem tenant atual (super admin sem "atuar como"), nao ha o que checar --
     * antes disso, SolicitacaoLocacao::assetIdsComReservaUrgente() era
     * chamado com Tenancy::current()?->id ?? '' e quebrava em runtime
     * (Postgres rejeita string vazia como uuid: "invalid input syntax for
     * type uuid").
     */
    private static function assetTemUrgenciaLocacao(?string $assetId): bool
    {
        $tenantId = Tenancy::current()?->id;

        if (! $assetId || ! $tenantId) {
            return false;
        }

        return in_array($assetId, SolicitacaoLocacao::assetIdsComReservaUrgente($tenantId), true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Fluxo Oravel')->tabs([

                // --- ABA 1: DADOS GERAIS ---
                Forms\Components\Tabs\Tab::make('Dados Gerais')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('asset_id')
                            ->label('Ativo / QR Code')
                            ->placeholder('Bipe o código ou digite Pat/Série/Tag')
                            ->required()->searchable()->preload()->live()
                            ->getSearchResultsUsing(function (string $search) {
                                $tenantId = Tenancy::current()?->id;
                                if (! $tenantId) {
                                    return [];
                                }

                                return Asset::where('tenant_id', $tenantId)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")->orWhere('patrimonio', 'like', "%{$search}%");
                                    })->limit(50)->get()->mapWithKeys(fn ($asset) => [$asset->id => "{$asset->patrimonio} — {$asset->name}"]);
                            })
                            // Sem isso, ao ABRIR uma OS existente pra editar (nao
                            // durante a busca ao vivo, que ja formata certo via
                            // getSearchResultsUsing acima) o Select mostrava o UUID
                            // cru do Ativo -- lia como "campo quebrado" pro usuario,
                            // sendo justamente o campo mais importante da tela.
                            ->getOptionLabelUsing(fn ($value) => ($asset = Asset::find($value)) ? "{$asset->patrimonio} — {$asset->name}" : null)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $asset = Asset::find($state);
                                    if ($asset) {
                                        $set('horimetro_anterior', $asset->last_horimetro ?? 0);
                                    }
                                }
                            })->prefixIcon('heroicon-m-qr-code'),
                        Forms\Components\Select::make('maintenance_type')
                            ->label('Tipo de Operação')
                            ->options(fn () => array_filter([
                                'Check-in' => 'Check-in (Mobilização)',
                                'Check-out' => 'Check-out (Desmobilização)',
                                'Preventiva' => 'Manutenção Preventiva',
                                'Corretiva' => 'Manutenção Corretiva',
                                'Avaria' => 'Registro de Avaria',
                                'Troca' => 'Troca de Equipamento',
                                'Emergência' => 'Chamado de Emergência',
                            ], fn ($key) => $key !== 'Emergência' || (Tenancy::current()?->hasModuleEnabled('sla_emergencia') ?? true), ARRAY_FILTER_USE_KEY))
                            ->required()->native(false)->live(),
                    ]),

                    // "Registro de Avaria" como Tipo de Operacao -- ate aqui so' existiam
                    // 2 jeitos indiretos de criar uma EquipmentDamage (checklist mobile de
                    // movimentacao, ou o atalho via foto-de-evidencia com severidade
                    // "avaria" dentro do repeater de evidencias, ver StoresPhotoEvidence).
                    // Esta secao cobre o caso direto: abrir a OS ja com a intencao de
                    // registrar uma avaria, sem precisar de foto. Reusa 'description' (ja
                    // obrigatorio na OS) como descricao da avaria -- sem duplicar campo.
                    // Campos nao-persistidos (dehydrated(false)): CreateMaintenanceOrder::afterCreate()
                    // / EditMaintenanceOrder::afterSave() leem via getRawState() e criam o
                    // EquipmentDamage.
                    Forms\Components\Section::make('Registro de Avaria')
                        ->description('Detalhes da avaria detectada nesta O.S.')
                        ->visible(fn (Get $get) => $get('maintenance_type') === MaintenanceOrder::TYPE_AVARIA)
                        ->schema([
                            Forms\Components\Select::make('damage_severity')
                                ->label('Severidade')
                                ->options([
                                    EquipmentDamage::SEVERITY_LEVE => 'Leve',
                                    EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                                    EquipmentDamage::SEVERITY_GRAVE => 'Grave',
                                ])
                                ->default(EquipmentDamage::SEVERITY_MODERADA)
                                // Sem required(): so' importa na 1a vez que a OS e' salva
                                // como Avaria (ver CreatesDamageFromAvariaType, que ja tem
                                // fallback pra esses defaults). Exigir preenchimento toda
                                // vez travaria edicoes seguintes da mesma OS, ja que estes
                                // 2 campos nao sao persistidos (dehydrated(false)) e voltam
                                // vazios ao reabrir o formulario.
                                ->dehydrated(false),
                            Forms\Components\Select::make('damage_type')
                                ->label('Tipo de Avaria')
                                ->options(EquipmentDamage::damageTypeLabels())
                                ->default(EquipmentDamage::DAMAGE_TYPE_OUTRO)
                                ->dehydrated(false),
                        ])
                        ->columns(2),

                    // Prazo Fatal (locadoras de evento) -- disponivel em QUALQUER OS,
                    // nao trava nada por conta propria (o checklist de mobilizacao ja
                    // e' bloqueante em 100%, ver EquipmentMovementMobile::finalize()).
                    // O toggle so' liga o alerta visual/contagem regressiva no Kanban.
                    Forms\Components\Section::make('Prazo Fatal')
                        ->description('Use quando esta O.S. está vinculada a um compromisso com data/hora que não pode atrasar (ex: montagem de evento).')
                        ->collapsed(fn (Get $get) => ! $get('is_prazo_fatal'))
                        ->schema([
                            Forms\Components\Toggle::make('is_prazo_fatal')
                                ->label('Esta O.S. tem prazo fatal')
                                ->live()
                                ->default(fn (Get $get) => Asset::find($get('asset_id'))?->client?->activity_type === Client::NICHE_EVENTOS)
                                ->visible(fn () => Tenancy::current()?->hasModuleEnabled('prazo_fatal') ?? true),
                            Forms\Components\DateTimePicker::make('prazo_fatal_at')
                                ->label('Prazo (data/hora limite)')
                                ->required(fn (Get $get) => (bool) $get('is_prazo_fatal'))
                                ->visible(fn (Get $get) => (bool) $get('is_prazo_fatal')),
                        ])
                        ->columns(2),

                    // Retrabalho -- campos is_rework/parent_os_id ja existiam no schema
                    // desde 2026-05-05, mas sem lugar nenhum na tela pra preencher
                    // (achado durante o levantamento de prontidao comercial,
                    // 2026-07-19): coluna morta ate agora.
                    Forms\Components\Section::make('Retrabalho')
                        ->description('Marque quando esta O.S. e a continuacao de um servico que nao ficou resolvido de primeira no mesmo ativo.')
                        ->collapsed(fn (Get $get) => ! $get('is_rework'))
                        ->schema([
                            Forms\Components\Toggle::make('is_rework')
                                ->label('Esta O.S. e um retrabalho')
                                ->live(),
                            Forms\Components\Select::make('parent_os_id')
                                ->label('O.S. original')
                                ->relationship(
                                    name: 'parentOrder',
                                    titleAttribute: 'os_number',
                                    modifyQueryUsing: fn (Builder $query, Get $get, ?MaintenanceOrder $record) => $query
                                        ->when($get('asset_id'), fn (Builder $q, $assetId) => $q->where('asset_id', $assetId))
                                        ->when($record, fn (Builder $q) => $q->where('id', '!=', $record->id)),
                                )
                                ->searchable()
                                ->preload()
                                ->required(fn (Get $get) => (bool) $get('is_rework'))
                                ->visible(fn (Get $get) => (bool) $get('is_rework')),
                        ])
                        ->columns(2),

                    // "Troca de Equipamento" como Tipo de Operacao -- entrada principal
                    // da Troca (confirmado: geralmente solicitada pelo tecnico em
                    // campo), mesmo padrao da secao de Avaria acima. So' pede a
                    // urgencia aqui; identificar substituto e iniciar movimentacoes
                    // acontece depois em EquipmentReplacementResource. Campo
                    // nao-persistido: CreatesReplacementFromOsType le via getRawState().
                    Forms\Components\Section::make('Solicitação de Troca de Equipamento')
                        ->description('O ativo selecionado acima será o "ativo original" da troca.')
                        ->visible(fn (Get $get) => $get('maintenance_type') === MaintenanceOrder::TYPE_TROCA)
                        ->schema([
                            Forms\Components\Select::make('replacement_urgency')
                                ->label('Urgência')
                                ->options([
                                    EquipmentReplacement::URGENCY_NORMAL => 'Normal',
                                    EquipmentReplacement::URGENCY_URGENTE => 'Urgente',
                                    EquipmentReplacement::URGENCY_CRITICO => 'Crítico',
                                ])
                                ->default(EquipmentReplacement::URGENCY_NORMAL)
                                ->dehydrated(false),
                        ]),

                    // "Chamado de Emergência" (locadoras industrial/hospitalar) --
                    // sla_target_minutes vira o relogio pro badge de cor (ver
                    // MaintenanceOrder::slaColor(), mesmo padrao de
                    // EquipmentReplacementResource::slaColor(), so' que em minutos e
                    // contado a partir de created_at, nao de uma janela fixa por
                    // urgencia). Sugestao de prazo pelo nivel de criticidade do Ativo
                    // (mesma fonte ja usada pro badge vermelho do Kanban) -- sempre
                    // editavel, nunca trava a criacao da OS.
                    Forms\Components\Section::make('Chamado de Emergência')
                        ->description('SLA de atendimento contado a partir da abertura desta O.S.')
                        ->visible(fn (Get $get) => $get('maintenance_type') === MaintenanceOrder::TYPE_EMERGENCIA
                            && (Tenancy::current()?->hasModuleEnabled('sla_emergencia') ?? true))
                        ->schema([
                            Forms\Components\TextInput::make('sla_target_minutes')
                                ->label('Prazo de Atendimento (minutos)')
                                ->numeric()
                                ->default(function (Get $get) {
                                    $asset = Asset::find($get('asset_id'));
                                    $nivel = $asset?->abcMatrix?->nivel;
                                    $urgente = $nivel && CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
                                        ->where('code', $nivel)
                                        ->value('is_urgent');

                                    return $urgente ? 60 : 240;
                                })
                                ->helperText('Sugerido pelo nível de criticidade do Ativo — pode ajustar.'),
                        ]),

                    // Se o ativo escolhido tem uma Solicitacao de Locacao urgente
                    // aguardando ele sair da manutencao (mesma condicao do banner
                    // "Locacao Urgente" no Kanban -- ver SolicitacaoLocacao::assetIdsComReservaUrgente()),
                    // criar uma OS nova aqui exige autorizacao do Supervisor de
                    // Manutencao (checado em CreateMaintenanceOrder::beforeCreate()).
                    Forms\Components\Placeholder::make('reserva_aviso')
                        ->label('')
                        ->visible(fn (Get $get) => static::assetTemUrgenciaLocacao($get('asset_id')))
                        ->content(new HtmlString(
                            '<div class="rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-300 dark:border-danger-700 px-4 py-3 text-sm text-danger-700 dark:text-danger-300">'
                            .'<strong>Atenção:</strong> este ativo está reservado por uma Locação urgente. '
                            .'Criar uma nova O.S. aqui exige autorização do Supervisor de Manutenção.'
                            .'</div>'
                        ))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('supervisor_override_password')
                        ->label('Senha do Supervisor de Manutenção (autorização)')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->visible(fn (Get $get) => static::assetTemUrgenciaLocacao($get('asset_id')))
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('grupo_display')
                        ->label('Grupo do Ativo')
                        ->content(function (Get $get) {
                            $asset = Asset::find($get('asset_id'));

                            return $asset?->checklistGroup?->name ?? 'Sem grupo definido';
                        }),

                    Forms\Components\Placeholder::make('preventivas_pendentes')
                        ->label('Preventivas Sugeridas (por Horímetro)')
                        ->visible(fn (Get $get) => (bool) $get('asset_id'))
                        ->content(function (Get $get) {
                            $asset = Asset::find($get('asset_id'));

                            if (! $asset) {
                                return 'Selecione um ativo.';
                            }

                            $plans = MaintenancePlan::applicableFor($asset)->where('is_active', true);

                            if ($plans->isEmpty()) {
                                return $asset->checklist_group_id
                                    ? 'Nenhum item de preventiva cadastrado para este grupo.'
                                    : 'Ativo sem grupo definido — sem template de preventiva.';
                            }

                            $lines = $plans->map(function ($plan) use ($asset) {
                                $status = $plan->dueStatusForAsset($asset);
                                $situacao = $status['is_overdue']
                                    ? 'VENCIDO há '.number_format($status['overdue_hours'], 0).'h'
                                    : 'Próxima em '.number_format($status['due_at_hours'] - (float) $asset->horimetro_atual, 0).'h';

                                return "{$plan->name}: trocado em {$status['last_service_hours']}h, horímetro atual {$asset->horimetro_atual}h, intervalo {$plan->interval_hours}h → {$situacao}";
                            });

                            return new HtmlString(
                                $lines->map(fn ($line) => '<div>'.e($line).'</div>')->implode('')
                            );
                        }),

                    // criticality_level_id (rotulado "Matriz ABC" mas era outra coisa --
                    // tabela criticality_levels solta, sem tela de cadastro, e o unico
                    // grafico que tentava agregar isso tinha um bug de comparacao de
                    // tipo). A Matriz ABC de verdade vive em Asset::abcMatrix(), que ja
                    // tem cadastro proprio (AbcMatrixResource) -- aqui so exibimos ela.
                    Forms\Components\Placeholder::make('matriz_abc_ativo')
                        ->key('matriz_abc_ativo_placeholder')
                        ->label('Matriz ABC do Ativo')
                        ->content(function (Get $get) {
                            $asset = $get('asset_id') ? Asset::find($get('asset_id')) : null;
                            $nivel = $asset?->abcMatrix?->nivel;

                            return $nivel
                                ? "Nível {$nivel}"
                                : new HtmlString('<span class="text-gray-400">Ativo sem Matriz ABC cadastrada.</span>');
                        })
                        ->hintAction(
                            // Matriz ABC continua sendo uma classificacao por ATIVO
                            // (unique(tenant_id, asset_id) no banco), nao por OS -- isso
                            // so' e' um atalho de criar/editar sem sair da tela da OS,
                            // via updateOrCreate mesma chave unica do AbcMatrixResource.
                            Forms\Components\Actions\Action::make('editar_matriz_abc')
                                ->label('Editar')
                                ->icon('heroicon-m-pencil-square')
                                ->visible(fn (Get $get) => (bool) $get('asset_id'))
                                ->form(fn (Get $get) => [
                                    Forms\Components\Select::make('nivel')
                                        ->label('Nível')
                                        ->options(fn () => CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
                                            ->orderBy('sort_order')
                                            ->pluck('name', 'code'))
                                        ->required()
                                        ->default(fn () => Asset::find($get('asset_id'))?->abcMatrix?->nivel),
                                    Forms\Components\Textarea::make('descricao')
                                        ->label('Descrição')
                                        ->required()
                                        ->default(fn () => Asset::find($get('asset_id'))?->abcMatrix?->descricao),
                                ])
                                ->action(function (array $data, Get $get) {
                                    $assetId = $get('asset_id');
                                    if (! $assetId) {
                                        return;
                                    }

                                    AbcMatrix::updateOrCreate(
                                        ['tenant_id' => Tenancy::current()?->id, 'asset_id' => $assetId],
                                        ['nivel' => $data['nivel'], 'descricao' => $data['descricao']]
                                    );

                                    Notification::make()->title('Matriz ABC atualizada')->success()->send();
                                })
                        ),

                    // Localizacao "onde o ativo esta instalado agora" -- se
                    // locado, vem do contrato vigente (Contract::resolvedLocation(),
                    // mesmo dado usado no Dossie Operacional e no cadastro do
                    // Ativo). Antes disso nao existia em lugar nenhum da OS.
                    Forms\Components\Placeholder::make('localizacao_ativo')
                        ->label('Localização do Ativo')
                        ->columnSpanFull()
                        ->content(function (Get $get) {
                            $asset = $get('asset_id') ? Asset::find($get('asset_id')) : null;

                            if (! $asset) {
                                return new HtmlString('<span class="text-gray-400">Selecione um ativo.</span>');
                            }

                            if ($asset->status !== Asset::STATUS_LOCADO) {
                                return new HtmlString('<span class="text-gray-400">Ativo não está locado — sem localização de contrato associada.</span>');
                            }

                            $location = $asset->activeContract()?->resolvedLocation();

                            if (! $location) {
                                return new HtmlString('<span class="text-gray-400">Ativo locado, mas sem localização definida no contrato vigente.</span>');
                            }

                            $condicao = $asset->activeContract()->condicao_ambiente;
                            $condicaoLabel = $condicao ? (Contract::condicaoOptions()[$condicao] ?? $condicao) : null;

                            $endereco = trim(($location['address'] ?? '').', '.($location['city'] ?? '').' - '.($location['uf'] ?? ''), ', -');

                            return new HtmlString(
                                '<div class="text-sm text-gray-700 dark:text-gray-300">'
                                .'<strong>'.e($location['label']).'</strong> — '.e($endereco ?: 'endereço não preenchido')
                                .($condicaoLabel ? ' <span class="ml-2 inline-block rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">'.e($condicaoLabel).'</span>' : '')
                                .'</div>'
                            );
                        }),

                    Forms\Components\TextInput::make('service_type')->label('Natureza do Serviço')->disabled()->dehydrated(true),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('horimetro_anterior')->label('Hor. Anterior')->numeric()->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('horimetro_entry')->label('Horímetro Atual')->numeric()->default(0)->required()->prefixIcon('heroicon-m-clock'),
                        Forms\Components\Select::make('fuel_level')->label('Nível Combustível')->options(['0' => 'Reserva', '25' => '1/4', '50' => '1/2', '75' => '3/4', '100' => 'Cheio'])->native(false),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('technician_id')->label('Responsável Técnico')->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))->required()->searchable(),
                        Forms\Components\Select::make('client_id')->label('Cliente')->relationship('client', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))->searchable(),
                        Forms\Components\DateTimePicker::make('scheduled_at')->label('Agendado para')->helperText('Aparece na Agenda Técnica.'),
                    ]),
                    Forms\Components\Select::make('status')
                        ->label('Status da OS')->options(['Aberto' => 'Aberto', 'Pendente' => 'Pendente', 'Em Andamento' => 'Em Andamento', 'Concluída' => 'Concluída', 'Cancelada' => 'Cancelada'])
                        ->default('Aberto')->disabled()->dehydrated(true),
                ]),

                // --- ABA 2: APONTAMENTOS ---
                Forms\Components\Tabs\Tab::make('Apontamentos')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DateTimePicker::make('started_at')->label('Início do Atendimento')->disabled()->dehydrated(true),
                        Forms\Components\DateTimePicker::make('finished_at')->label('Fim do Atendimento')->disabled()->dehydrated(true),
                    ]),
                    Forms\Components\Textarea::make('description')->label('Problema Relatado / Escopo do Serviço')->rows(3)->required()->hint(FormHelpers::voiceButton()),
                    Forms\Components\Textarea::make('technical_notes')->label('Notas Técnicas / Diagnóstico Executado')->rows(3)->hint(FormHelpers::voiceButton()),
                ]),

                // --- ABA 3: VISTORIA / CHECKLIST ---
                Forms\Components\Tabs\Tab::make('Vistoria / Checklist')
                    ->schema([
                        Forms\Components\Repeater::make('checklists')
                            ->relationship('checklists')
                            ->label('Checklist do Ativo (básico do Grupo + itens extras)')
                            ->schema([
                                Forms\Components\TextInput::make('item_name')->label('Item de Inspeção')->disabled()->dehydrated(true),
                                Forms\Components\ToggleButtons::make('status')
                                    ->label('Conformidade')
                                    ->options(['conforme' => 'Conforme', 'nao_conforme' => 'Não Conforme', 'nao_aplicavel' => 'N/A'])
                                    ->colors(['conforme' => 'success', 'nao_conforme' => 'danger', 'nao_aplicavel' => 'gray'])
                                    ->inline(),
                                Forms\Components\TextInput::make('notes')->label('Observações / Evidência'),
                                // Reproduzido no PROD 2026-07-29: tirar foto na vistoria
                                // falhava com "The data.checklists.record-X.photos... failed
                                // to upload". Recusa por TAMANHO, e o PROD tinha dois tetos --
                                // client_max_body_size do nginx, que nem estava configurado e
                                // portanto valia o default de 1MB (o mais apertado dos dois, e
                                // o que corta antes de chegar no PHP), e upload_max_filesize=2M
                                // do PHP. Os dois foram levantados no servidor, mas config de
                                // servidor sozinha nao resolve: teria que ser refeita em todo
                                // ambiente que hospeda o app, e subir 3-8MB por foto e'
                                // desperdicio de banda/disco pra uma evidencia de vistoria.
                                // Entao o FilePond tambem redimensiona no proprio navegador
                                // antes de enviar: 'contain' + 1600x1600 = lado maior de 1600px
                                // sem cortar nada (o default 'cover' do Filament CORTARIA a
                                // foto pra preencher 1600x1600 exatos), e uma foto tipica de
                                // 5MB sai em ~300-700KB. upscale(false) pra nao inflar foto
                                // pequena. NAO adicionar ->maxSize() pequeno aqui: o FilePond
                                // valida tamanho no arquivo ORIGINAL, antes do resize, e
                                // rejeitaria justamente a foto que isso conserta.
                                Forms\Components\SpatieMediaLibraryFileUpload::make('photos')
                                    ->collection('photos')
                                    ->label('Foto')
                                    ->image()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1600')
                                    ->imageResizeTargetHeight('1600')
                                    ->imageResizeUpscale(false),
                            ])->columns(3)->disableItemCreation()->disableItemDeletion(),
                    ]),

                // --- ABA 4: FOTOS ---
                Forms\Components\Tabs\Tab::make('Fotos e Evidências')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        CameraCapture::make('photo_before')
                            ->label('Foto ANTES do Serviço (Estado Inicial)'),
                        CameraCapture::make('photo_after')
                            ->label('Foto DEPOIS do Serviço (Resultado Final)'),
                    ]),

                    Forms\Components\Repeater::make('extra_evidences')
                        ->label('Evidências Adicionais')
                        ->dehydrated()
                        ->addActionLabel('Adicionar Evidência')
                        ->schema([
                            CameraCapture::make('photo')
                                ->label('Foto'),
                            Forms\Components\TextInput::make('category')
                                ->label('Categoria')
                                ->datalist(['Painel/Horímetro', 'Estrutura Geral', 'Avaria', 'Mau Uso'])
                                ->placeholder('Ex: Painel/Horímetro, Avaria: Esteira Esquerda'),
                            Forms\Components\ToggleButtons::make('severity')
                                ->label('Severidade')
                                ->options(fn () => array_filter(
                                    ['ok' => 'OK', 'avaria' => 'Avaria', 'mau_uso' => 'Mau Uso'],
                                    fn ($key) => $key !== 'mau_uso' || (Tenancy::current()?->hasModuleEnabled('mau_uso') ?? true),
                                    ARRAY_FILTER_USE_KEY
                                ))
                                ->colors(['ok' => 'success', 'avaria' => 'danger', 'mau_uso' => 'warning'])
                                ->default('ok')->inline()->live(),
                            // Marcar "Avaria" OU "Mau Uso" aqui ja cria um EquipmentDamage
                            // de verdade (ver StoresPhotoEvidence::persistPhotoEvidences())
                            // -- antes so' "avaria" fazia isso; "Mau Uso" (locadoras de
                            // construcao civil, evidencia pra cobranca/contestacao com o
                            // cliente da obra) reusa o mesmo fluxo, so' com severidade
                            // diferente pra distinguir na listagem/comparativo Antes/Depois.
                            Forms\Components\Select::make('damage_severity')
                                ->label('Gravidade')
                                ->options([
                                    EquipmentDamage::SEVERITY_LEVE => 'Leve',
                                    EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                                    EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                                ])
                                ->default(EquipmentDamage::SEVERITY_MODERADA)
                                ->required(fn (Get $get) => in_array($get('severity'), ['avaria', 'mau_uso'], true))
                                ->visible(fn (Get $get) => in_array($get('severity'), ['avaria', 'mau_uso'], true)),
                            Forms\Components\Select::make('damage_type')
                                ->label('Tipo')
                                ->options(EquipmentDamage::damageTypeLabels())
                                ->required(fn (Get $get) => in_array($get('severity'), ['avaria', 'mau_uso'], true))
                                ->visible(fn (Get $get) => in_array($get('severity'), ['avaria', 'mau_uso'], true)),
                            Forms\Components\Textarea::make('observation')
                                ->label('Observação')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

                // --- ABA 5: MATERIAIS ---
                Forms\Components\Tabs\Tab::make('Materiais')->schema([
                    Forms\Components\Repeater::make('materials')
                        ->relationship('materials')
                        ->schema([
                            Forms\Components\Select::make('material_id')->relationship('material', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))->required()->searchable(),
                            Forms\Components\TextInput::make('quantity')->label('Qtd')->numeric()->default(1)->required(),
                        ])->columns(2),
                ]),

                // --- ABA 5B: CUSTOS ---
                // Colunas reais (labor_cost/material_cost/logistics_cost/
                // total_order_cost), mas ate agora sem nenhum campo em
                // nenhuma aba da O.S. -- so existia leitura disso no
                // dossie do Ativo. total_order_cost nao e calculado
                // automaticamente em nenhum lugar do app, entao os 4
                // ficam editaveis manualmente, igual as outras colunas.
                Forms\Components\Tabs\Tab::make('Custos')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('labor_cost')
                            ->label('Mão de Obra (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('material_cost')
                            ->label('Material (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('logistics_cost')
                            ->label('Logística (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('total_order_cost')
                            ->label('Custo Total (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                    ]),
                ]),

                // --- ABA 6: ASSINATURAS ---
                Forms\Components\Tabs\Tab::make('Assinaturas Digitais')->schema([
                    Forms\Components\Placeholder::make('info_sig')->content('Colete a assinatura na tela do dispositivo.'),
                    Forms\Components\Grid::make(2)->schema([
                        SignaturePad::make('technician_signature')
                            ->label('Assinatura do Técnico')
                            ->loadStrategy('idle'),
                        SignaturePad::make('client_signature')
                            ->label('Assinatura do Cliente')
                            ->loadStrategy('idle'),
                    ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->poll('10s')->columns([
            static::tenantColumn(),
            Tables\Columns\TextColumn::make('asset.patrimonio')->label('Patrimônio')->weight('bold')->searchable(),
            Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y'),
            Tables\Columns\TextColumn::make('os_number')->label('Nº OS')->searchable()->weight('bold'),
            Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable(),
            Tables\Columns\TextColumn::make('asset.assetCategory.name')
                ->label('Categoria do Ativo')
                ->sortable()
                ->placeholder('Sem Categoria'),
            Tables\Columns\TextColumn::make('asset.checklistGroup.name')
                ->label('Grupo')
                ->badge()
                ->color('gray')
                ->placeholder('Sem grupo'),
            Tables\Columns\TextColumn::make('asset.abcMatrix.nivel')
                ->label('Matriz ABC')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => $state
                    ? (CriticalityLevel::where('tenant_id', Tenancy::current()?->id)->where('code', $state)->value('name') ?? $state)
                    : '-')
                ->color(fn (?string $state): string => $state && CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
                    ->where('code', $state)
                    ->value('is_urgent') ? 'danger' : 'gray')
                ->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('is_rework')
                ->label('Retrabalho')
                ->badge()
                ->color('danger')
                ->icon('heroicon-o-arrow-path')
                ->formatStateUsing(fn (bool $state): ?string => $state ? 'Retrabalho' : null)
                ->toggleable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')
                ->label('Status')
                ->multiple()
                ->options([
                    'Aberto' => 'Aberto',
                    'Pendente' => 'Pendente',
                    'Em Andamento' => 'Em Andamento',
                    'Concluída' => 'Concluída',
                    'Cancelada' => 'Cancelada',
                ]),
            Tables\Filters\SelectFilter::make('maintenance_type')
                ->label('Tipo')
                ->options(fn () => array_filter([
                    'Corretiva' => 'Corretiva',
                    'Preventiva' => 'Preventiva',
                    'Avaria' => 'Registro de Avaria',
                    'Troca' => 'Troca de Equipamento',
                    'Emergência' => 'Chamado de Emergência',
                ], fn ($key) => $key !== 'Emergência' || (Tenancy::current()?->hasModuleEnabled('sla_emergencia') ?? true), ARRAY_FILTER_USE_KEY)),
            Tables\Filters\SelectFilter::make('matriz_abc')
                ->label('Matriz ABC')
                ->options(fn () => CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
                    ->orderBy('sort_order')
                    ->pluck('name', 'code'))
                ->query(fn (Builder $query, array $data) => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $q, $nivel) => $q->whereHas('asset.abcMatrix', fn (Builder $q2) => $q2->where('nivel', $nivel))
                )),
            Tables\Filters\SelectFilter::make('asset.checklist_group_id')
                ->label('Grupo')
                ->relationship('asset.checklistGroup', 'name'),
            Tables\Filters\SelectFilter::make('technician_id')
                ->label('Técnico')
                ->relationship('technician', 'name'),
            Tables\Filters\Filter::make('atrasada')
                ->label('Em atraso (+3 dias aberta)')
                ->toggle()
                ->query(fn (Builder $query) => $query
                    ->whereIn('status', ['Aberto', 'Pendente', 'Em Andamento'])
                    ->where('created_at', '<', now()->subDays(3))),
            Tables\Filters\Filter::make('is_rework')
                ->label('Só retrabalho')
                ->toggle()
                ->query(fn (Builder $query) => $query->where('is_rework', true)),

        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    // GATILHO DE NOTIFICAÇÃO
    public static function afterCreate($record): void
    {
        Notification::make()
            ->title('Nova O.S. Gerada')
            ->body("A O.S. #{$record->id} foi criada para o ativo {$record->asset?->name}.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->info()
            ->sendToDatabase(auth()->user());
    }

    public static function getWidgets(): array
    {
        return [];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PendenciasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceOrders::route('/'),
            'create' => Pages\CreateMaintenanceOrder::route('/create'),
            'edit' => Pages\EditMaintenanceOrder::route('/{record}/edit'),
        ];
    }
}
