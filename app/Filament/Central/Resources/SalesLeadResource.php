<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\SalesLeadResource\Pages;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\AppointmentsRelationManager;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\InteractionsRelationManager;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\TimelineRelationManager;
use App\Models\Client;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\User;
use App\Services\CepGeocodingService;
use App\Support\CrmPalette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class SalesLeadResource extends Resource
{
    protected static ?string $model = SalesLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'CRM Comercial';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Lead Comercial';

    protected static ?string $pluralModelLabel = 'Leads Comerciais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Empresa e contato')
                ->icon('heroicon-o-building-office')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Empresa')
                        ->required(),
                    Forms\Components\TextInput::make('website')
                        ->label('Site')
                        ->url()
                        ->prefix('https://'),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone')
                        ->tel(),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email(),
                    Forms\Components\Repeater::make('decision_makers')
                        ->label('Tomadores de Decisão')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome')
                                ->required(),
                            Forms\Components\TextInput::make('role')
                                ->label('Cargo'),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Adicionar tomador de decisão')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Venda')
                ->icon('heroicon-o-currency-dollar')
                ->description('Valor, responsável e o argumento de venda desta empresa.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('estimated_contract_value')
                        ->label('Valor Estimado do Contrato (MRR)')
                        ->numeric()
                        ->prefix('R$'),
                    Forms\Components\Select::make('assigned_user_id')
                        ->label('Responsável')
                        ->options(fn () => User::whereIn('email', config('oravel.super_admins', []))->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('critical_pain')
                        ->label('Dor Crítica Mapeada')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('oravel_solution')
                        ->label('Solução Oravel')
                        ->helperText('O que o sistema oferece pra resolver essa dor específica -- vira a base do argumento de venda.')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('outreach_email_draft')
                        ->label('Rascunho do E-mail de Prospecção (D3)')
                        ->helperText('Texto personalizado pronto pra copiar e enviar -- fica salvo aqui pra não se perder numa conversa avulsa, e sai junto na exportação.')
                        ->rows(6)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Classificação')
                ->icon('heroicon-o-tag')
                ->description('Segmento e de onde veio o lead.')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Forms\Components\Select::make('segment')
                        ->label('Segmento Principal')
                        // Mesmos valores de tenants.segment/Client::NICHE_* de
                        // proposito -- quando converte, o Tenant nasce com o
                        // segmento certo, sem vocabulario paralelo. Fica
                        // como o valor UNICO usado pelo resto do sistema
                        // (Kanban, mapa, dashboards) -- segmentos extras vao
                        // no repeater abaixo, sem afetar quem depende desse.
                        ->options(Client::nicheLabels()),
                    Forms\Components\Repeater::make('additional_segments')
                        ->label('Outros Segmentos')
                        ->simple(
                            Forms\Components\TextInput::make('segment')
                                ->datalist(array_values(Client::nicheLabels()))
                                ->required(),
                        )
                        ->addActionLabel('+ Adicionar segmento')
                        ->defaultItems(0)
                        ->columnSpan(2),
                    Forms\Components\Select::make('source')
                        ->label('Origem Principal')
                        ->options(SalesLead::sourceLabels()),
                    Forms\Components\Repeater::make('additional_sources')
                        ->label('Outras Origens')
                        ->simple(
                            Forms\Components\TextInput::make('source')
                                ->datalist(array_values(SalesLead::sourceLabels()))
                                ->required(),
                        )
                        ->addActionLabel('+ Adicionar origem')
                        ->defaultItems(0)
                        ->columnSpan(2),
                ]),

            Forms\Components\Section::make('Recebido pelo formulário do site')
                ->description('Mensagem do visitante e de onde ele veio. Preenchido automaticamente; só leitura.')
                ->visible(fn (?SalesLead $record) => filled($record?->inbound_message) || filled($record?->inbound_details))
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('received_at')
                        ->label('Recebido em')
                        ->content(fn (?SalesLead $record) => $record?->created_at?->format('d/m/Y H:i')),
                    Forms\Components\Placeholder::make('visitor_place')
                        ->label('Cidade / Estado do visitante')
                        ->content(fn (?SalesLead $record) => collect([$record?->city, $record?->uf])->filter()->implode(' / ') ?: 'Não identificado'),
                    Forms\Components\Placeholder::make('came_from')
                        ->label('Por onde veio')
                        ->content(fn (?SalesLead $record) => $record?->inbound_details['veio_de'] ?? 'Não identificado'),
                    Forms\Components\Placeholder::make('contact_email')
                        ->label('E-mail e telefone')
                        ->content(fn (?SalesLead $record) => collect([$record?->email, $record?->phone])->filter()->implode(' · ') ?: '—'),
                    Forms\Components\Placeholder::make('inbound_message_view')
                        ->label('Mensagem do visitante')
                        ->content(fn (?SalesLead $record) => $record?->inbound_message ?: '—')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('inbound_details_view')
                        ->label('Origem (página, anúncio, campanha)')
                        ->content(fn (?SalesLead $record) => new HtmlString(
                            collect($record?->inbound_details ?? [])->map(fn ($v, $k) => '<div><strong>'.e(str_replace('_', ' ', (string) $k)).':</strong> '.e((string) $v).'</div>')->implode('')
                        ))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Endereço')
                ->icon('heroicon-o-map-pin')
                ->description('Preencha o CEP: endereço e localização no mapa são preenchidos automaticamente.')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('cep')
                        ->label('CEP')
                        ->placeholder('00000-000')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $service = app(CepGeocodingService::class);
                            $endereco = $service->lookupCep($state);

                            if (! $endereco) {
                                Notification::make()->title('CEP não encontrado.')->warning()->send();

                                return;
                            }

                            $set('address', $endereco['address']);
                            $set('city', $endereco['city']);
                            $set('uf', $endereco['uf']);

                            $fullAddress = trim($endereco['address'].', '.$endereco['city'].' - '.$endereco['uf']);
                            $coords = $service->geocodeAddress($fullAddress);

                            if ($coords) {
                                $set('latitude', $coords['latitude']);
                                $set('longitude', $coords['longitude']);
                                Notification::make()->title('Endereço localizado no mapa.')->success()->send();
                            } else {
                                $set('latitude', null);
                                $set('longitude', null);
                                Notification::make()->title('Endereço preenchido, mas não foi possível localizar no mapa automaticamente.')->warning()->send();
                            }
                        }),
                    Forms\Components\TextInput::make('address')->label('Endereço')->columnSpan(2),
                    Forms\Components\TextInput::make('city')->label('Cidade'),
                    Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                    Forms\Components\TextInput::make('latitude')->label('Latitude')->numeric()->disabled()->dehydrated(),
                    Forms\Components\TextInput::make('longitude')->label('Longitude')->numeric()->disabled()->dehydrated(),
                ]),

            Forms\Components\Section::make('Funil')
                ->icon('heroicon-o-funnel')
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('pipeline_stage_display')
                        ->label('Estágio Atual')
                        ->content(fn (?SalesLead $record) => $record
                            ? (SalesLead::stageLabels()[$record->pipeline_stage] ?? $record->pipeline_stage)
                            : SalesLead::stageLabels()[SalesLead::STAGE_PROSPECCAO])
                        ->helperText('O estágio só avança pelas ações "Avançar Estágio" / "Marcar como Perdido" / "Converter em Tenant" -- não é editável direto, de propósito.'),
                    Forms\Components\Select::make('lost_reason')
                        ->label('Motivo da Perda')
                        ->options(SalesLead::lostReasonLabels())
                        ->visible(fn (?SalesLead $record) => $record?->pipeline_stage === SalesLead::STAGE_PERDIDO)
                        ->disabled(),
                    Forms\Components\Textarea::make('lost_reason_detail')
                        ->label('Detalhe da Perda')
                        ->visible(fn (?SalesLead $record) => $record?->pipeline_stage === SalesLead::STAGE_PERDIDO)
                        ->disabled()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Ícone por estágio -- reforça a diferenciação visual junto com a cor
     * (não depende só de cor pra "escanear" a lista rápido, também ajuda
     * quem tem dificuldade de perceber a diferença entre tons próximos).
     */
    private static function stageIcon(string $stage): string
    {
        return match ($stage) {
            SalesLead::STAGE_PROSPECCAO => 'heroicon-o-magnifying-glass',
            SalesLead::STAGE_CONTATO_QUALIFICADO => 'heroicon-o-chat-bubble-left-right',
            SalesLead::STAGE_DEMONSTRACAO_REALIZADA => 'heroicon-o-presentation-chart-line',
            SalesLead::STAGE_PROPOSTA_ENVIADA => 'heroicon-o-document-text',
            SalesLead::STAGE_GANHO => 'heroicon-o-check-badge',
            SalesLead::STAGE_PERDIDO => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            // Borda lateral + fundo translúcido na linha inteira, mesma cor
            // do estágio (CrmPalette::stage()['border']/['soft']) -- o badge
            // sozinho é discreto demais numa lista longa; a linha inteira dá
            // pra "escanear" a tabela de relance. Pedido do usuário
            // 2026-08-04/05: "tudo branco, sem distinguir estágio" + fundo
            // "bem mais claro, baixa opacidade, só destaque sutil".
            // hover:bg-transparent sobrescreve o hover cinza padrão do
            // Filament, que senão apagava a cor de fundo ao passar o mouse.
            // Grid estilo planilha (pedido 2026-08-19: "grid semelhante a
            // uma planilha do Excel") vem via CSS em
            // resources/css/filament/central/theme.css, escopado pela
            // classe .fi-resource-sales-leads que o Filament ja' gera
            // sozinho pro wrapper deste Resource (Table::extraAttributes()
            // nao existe nesta versao do Filament) -- mantem a cor por
            // estagio (nao remove, so' soma linhas de grade por cima).
            ->recordClasses(fn (SalesLead $record) => 'border-s-4 hover:!bg-transparent '
                .CrmPalette::stage($record->pipeline_stage)['border'].' '
                .CrmPalette::stage($record->pipeline_stage)['soft'])
            ->columns([
                // Layout em CARTOES (pedido 2026-11-01: lista "muito ruim e pouco intuitiva", queria o
                // estilo do acompanhamento de Prospects). Cada lead e' um cartao: quem e', como falar,
                // em que etapa esta e quando foi o ultimo contato / quando voltar.
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('company_name')
                                ->label('Empresa')
                                ->searchable()
                                ->weight('bold')
                                ->size('lg')
                                ->color('crmOrange'),
                            Tables\Columns\TextColumn::make('city')
                                ->label('Cidade / UF')
                                ->formatStateUsing(fn ($state, SalesLead $record) => collect([$record->city, $record->uf])->filter()->implode(' / ') ?: null)
                                ->color('gray')
                                ->placeholder('—'),
                        ]),
                        Tables\Columns\TextColumn::make('segment')
                            ->label('Segmento')
                            ->badge()
                            ->color(fn (?string $state) => CrmPalette::segment($state)['filament'])
                            ->formatStateUsing(fn (?string $state) => $state ? (Client::nicheLabels()[$state] ?? $state) : null)
                            ->grow(false),
                    ]),
                    Tables\Columns\SelectColumn::make('pipeline_stage')
                        ->label('Estágio')
                        ->options(SalesLead::stageLabels())
                        ->selectablePlaceholder(false),
                    Tables\Columns\TextColumn::make('phone')
                        ->label('Telefone')
                        ->icon('heroicon-m-phone')
                        ->copyable()
                        ->copyMessage('Telefone copiado')
                        ->placeholder('Sem telefone'),
                    Tables\Columns\TextColumn::make('email')
                        ->label('E-mail')
                        ->icon('heroicon-m-envelope')
                        ->copyable()
                        ->copyMessage('E-mail copiado')
                        ->placeholder('Sem e-mail'),
                    Tables\Columns\TextColumn::make('decision_makers')
                        ->label('Contato')
                        ->icon('heroicon-m-user')
                        ->color('gray')
                        ->state(fn (SalesLead $record) => ($dm = $record->primaryDecisionMaker())
                            ? trim(($dm['name'] ?? '').(filled($dm['role'] ?? null) ? ' ('.$dm['role'].')' : ''))
                            : null)
                        ->placeholder('Sem contato informado'),
                    Tables\Columns\TextInputColumn::make('estimated_contract_value')
                        ->label('Valor Estimado')
                        ->type('number')
                        ->rules(['nullable', 'numeric', 'min:0'])
                        ->placeholder('Valor estimado (R$)'),
                    Tables\Columns\TextColumn::make('last_interaction_at')
                        ->label('Última Interação')
                        ->icon('heroicon-m-clock')
                        ->formatStateUsing(fn ($state) => 'Último contato: '.$state->format('d/m/Y').' ('.$state->diffForHumans().')')
                        ->placeholder('Nunca contatado')
                        ->color(fn (?SalesLead $record) => $record?->isOpen() && (! $record->last_interaction_at || $record->last_interaction_at->diffInDays(now()) >= 3) ? 'danger' : 'gray'),
                    Tables\Columns\TextColumn::make('next_followup_date')
                        ->label('Retorno')
                        ->icon('heroicon-m-calendar-days')
                        ->formatStateUsing(fn ($state) => 'Retornar em '.$state->format('d/m/Y'))
                        ->visible(fn () => true)
                        ->placeholder('Sem retorno agendado')
                        ->color(fn (?SalesLead $record) => $record?->isOpen() && $record->next_followup_date && $record->next_followup_date->lte(now()) ? 'danger' : 'gray'),
                    Tables\Columns\SelectColumn::make('assigned_user_id')
                        ->label('Responsável')
                        ->options(fn () => User::whereIn('email', config('oravel.super_admins', []))->pluck('name', 'id'))
                        ->placeholder('Responsável'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->searchPlaceholder('Buscar empresa, telefone, e-mail ou cidade')
            ->filters([
                // Filtro por coluna, pedido do usuário 2026-08-05 -- um
                // filtro pra cada coluna visível da tabela, não só
                // Estágio/Segmento como antes.
                Tables\Filters\SelectFilter::make('pipeline_stage')
                    ->label('Estágio')
                    ->options(SalesLead::stageLabels()),
                Tables\Filters\SelectFilter::make('segment')
                    ->label('Segmento')
                    ->options(Client::nicheLabels()),
                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Responsável')
                    ->options(fn () => User::whereIn('email', config('oravel.super_admins', []))->pluck('name', 'id')),
                Tables\Filters\Filter::make('estimated_contract_value')
                    ->label('Valor Estimado')
                    ->form([
                        Forms\Components\TextInput::make('valor_min')->label('De (R$)')->numeric(),
                        Forms\Components\TextInput::make('valor_max')->label('Até (R$)')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['valor_min'] ?? null, fn ($q, $v) => $q->where('estimated_contract_value', '>=', $v))
                            ->when($data['valor_max'] ?? null, fn ($q, $v) => $q->where('estimated_contract_value', '<=', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['valor_min'] ?? null) {
                            $indicators[] = 'A partir de R$ '.number_format((float) $data['valor_min'], 2, ',', '.');
                        }
                        if ($data['valor_max'] ?? null) {
                            $indicators[] = 'Até R$ '.number_format((float) $data['valor_max'], 2, ',', '.');
                        }

                        return $indicators;
                    }),
                Tables\Filters\Filter::make('last_interaction_at')
                    ->label('Última Interação')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn ($q, $date) => $q->whereDate('last_interaction_at', '>=', $date))
                            ->when($data['ate'] ?? null, fn ($q, $date) => $q->whereDate('last_interaction_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['de'] ?? null) {
                            $indicators[] = 'De: '.Carbon::parse($data['de'])->format('d/m/Y');
                        }
                        if ($data['ate'] ?? null) {
                            $indicators[] = 'Até: '.Carbon::parse($data['ate'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                // Sem isso, os cards de estatistica da listagem ("Com
                // Contato"/"Sem Contato Direto") nao tem pra onde levar --
                // pedido do usuario 2026-08-10: card clicavel deve linkar
                // pro dado real no banco, nao so decorativo.
                Tables\Filters\Filter::make('sem_contato')
                    ->label('Sem contato há 3 dias ou mais')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotIn('pipeline_stage', [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO])
                        ->where(fn ($w) => $w->whereNull('last_interaction_at')->orWhere('last_interaction_at', '<=', now()->subDays(3)))),
                Tables\Filters\Filter::make('retorno_vencido')
                    ->label('Retorno vencido')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotIn('pipeline_stage', [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO])
                        ->whereNotNull('next_followup_date')->whereDate('next_followup_date', '<=', now())),
                Tables\Filters\TernaryFilter::make('has_contact')
                    ->label('Tem Contato Direto')
                    ->placeholder('Todos')
                    ->trueLabel('Com telefone ou e-mail')
                    ->falseLabel('Sem telefone e sem e-mail')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('email')),
                        false: fn (Builder $query) => $query->whereNull('phone')->whereNull('email'),
                    ),
                // Filtro por canal de interacao ja registrada (Follow Up) --
                // viabiliza o card "quantos/quais leads eu liguei/mandei
                // email/etc" (pedido do usuario 2026-08-10) linkar pra
                // listagem real, nao so' mostrar o numero.
                Tables\Filters\SelectFilter::make('interaction_channel')
                    ->label('Canal de Contato Já Feito')
                    ->options(SalesLeadInteraction::channelLabels())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $channel) => $q->whereHas('interactions', fn ($iq) => $iq->where('channel', $channel)),
                        );
                    }),
            ])
            // Filtros sempre visiveis numa linha acima do grid, em vez de
            // atras do botao "Filtros" -- pedido do usuario 2026-08-19
            // ("coloque acima do grid os list box com os filtros em cada
            // coluna"). filtersFormColumns() maior que o padrao (2) pra
            // caber os 6 filtros numa faixa horizontal em vez de empilhar.
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->filtersFormColumns(2)
            // Mantem o ultimo filtro/busca/ordenacao ao recarregar a pagina
            // (guardado na sessao do usuario) -- pedido do usuario.
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistSortInSession()
            ->actions([
                Tables\Actions\Action::make('registrar_contato')
                    ->label('Registrar contato')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (SalesLead $record) => $record->isOpen())
                    ->form([
                        Forms\Components\Select::make('channel')->label('Canal')->options(SalesLeadInteraction::channelLabels())->default(SalesLeadInteraction::CHANNEL_WHATSAPP)->required()->native(false),
                        Forms\Components\DateTimePicker::make('contact_date')->label('Quando')->default(now())->required()->native(false)->displayFormat('d/m/Y H:i'),
                        Forms\Components\Textarea::make('summary')->label('O que foi dito / combinado')->required()->rows(4),
                        Forms\Components\DatePicker::make('next_followup_date')->label('Próximo retorno (opcional)')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->action(function (SalesLead $record, array $data) {
                        $record->interactions()->create([
                            'user_id' => auth()->id(),
                            'channel' => $data['channel'],
                            'contact_date' => $data['contact_date'],
                            'summary' => $data['summary'],
                            'stage_at_time' => $record->pipeline_stage,
                        ]);
                        $record->refreshInteractionCache();
                        if (filled($data['next_followup_date'] ?? null)) {
                            $record->update(['next_followup_date' => $data['next_followup_date']]);
                        }
                        Notification::make()->title('Contato registrado.')->success()->send();
                    }),
                Tables\Actions\Action::make('advance')
                    ->label('Avançar Estágio')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('gray')
                    ->visible(fn (SalesLead $record) => $record->isOpen() && $record->nextStage() && $record->nextStage() !== SalesLead::STAGE_GANHO)
                    ->action(function (SalesLead $record) {
                        try {
                            $record->advanceStage();
                            Notification::make()->title('Estágio avançado.')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não foi possível avançar')->body($e->getMessage())->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('convert')
                    ->label('Converter em Tenant')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SalesLead $record) => $record->pipeline_stage === SalesLead::STAGE_PROPOSTA_ENVIADA)
                    ->form([
                        Forms\Components\Select::make('plan_id')
                            ->label('Plano')
                            ->options(fn () => Plan::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('admin_name')
                            ->label('Nome do Admin')
                            ->required(),
                        Forms\Components\TextInput::make('admin_email')
                            ->label('E-mail do Admin')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('admin_password')
                            ->label('Senha Inicial')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->mountUsing(fn (Form $form, SalesLead $record) => $form->fill([
                        'admin_name' => $record->primaryDecisionMaker()['name'] ?? null,
                        'admin_email' => $record->email,
                    ]))
                    ->action(function (SalesLead $record, array $data) {
                        try {
                            $record->convertToTenant($data['plan_id'], [
                                'name' => $data['admin_name'],
                                'email' => $data['admin_email'],
                                'password' => $data['admin_password'],
                            ]);
                            Notification::make()->title('Tenant criado com sucesso!')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não foi possível converter')->body($e->getMessage())->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('mark_lost')
                    ->label('Marcar como Perdido')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SalesLead $record) => $record->isOpen())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('lost_reason')
                            ->label('Motivo')
                            ->options(SalesLead::lostReasonLabels())
                            ->required(),
                        Forms\Components\Textarea::make('lost_reason_detail')
                            ->label('Detalhe'),
                    ])
                    ->action(fn (SalesLead $record, array $data) => $record->markLost($data['lost_reason'], $data['lost_reason_detail'] ?? null)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TimelineRelationManager::class,
            InteractionsRelationManager::class,
            AppointmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesLeads::route('/'),
            'create' => Pages\CreateSalesLead::route('/create'),
            'edit' => Pages\EditSalesLead::route('/{record}/edit'),
        ];
    }
}
