<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\ContratoResource\Pages;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\SaaSRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Caminho dedicado pra gerar o link público de assinatura (2026-09-23,
 * pedido do usuário: "quero uma tela nova, só pra gerar o link" --
 * ele não queria que esse fluxo continuasse vivendo dentro de "Planos",
 * já que não existe mais plano padrão de prateleira, cada cliente
 * negocia o próprio conjunto de módulos/valor). Rebatizado de "Proposta"
 * pra "Contrato" no mesmo dia (pedido do usuário: "o que é proposta é na
 * verdade contrato, mude tudo em todos os lugares") -- ESCOPO
 * CONFIRMADO: só esta tela nova da Central, não o módulo pré-existente
 * "Proposta Comercial" (vendas pra clientes finais, painel admin), que é
 * outra coisa e não foi tocado.
 *
 * Por baixo continua sendo o MESMO registro de Plan que PlanResource usa
 * (nenhuma tabela nova, nenhum dado duplicado) -- é só uma segunda porta
 * de entrada mais enxuta, focada só no essencial (identificação, valor,
 * ciclo, módulos) e que já mostra o link assim que o contrato é criado. A
 * tela de "Planos" continua existindo do jeito que estava (o usuário
 * pediu explicitamente pra manter as duas por enquanto).
 */
class ContratoResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Contratos';

    protected static ?string $modelLabel = 'Contrato';

    protected static ?string $pluralModelLabel = 'Contratos';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    /**
     * Módulos agrupados pelo MESMO agrupamento do menu lateral (pedido do
     * usuário 2026-09-23: "as tabelas assim como os menus estejam
     * agrupadas como menus e submenus... exemplo: Manutenção - OS,
     * Avarias") -- reaproveita SaaSRegistry::modulesGroupedByNavigation(),
     * que lê o $navigationGroup de cada Resource. 'modulo_dashboard' não
     * tem Model/Resource por trás (é só a página inicial), então entra
     * manualmente em 'Outros', igual já fazia Plan::getAvailableFeaturesOptions().
     *
     * @return array<string, array<string, string>> grupo => [feature_key => label]
     */
    public static function groupedFeatureOptions(): array
    {
        $grouped = [];

        foreach (SaaSRegistry::modulesGroupedByNavigation() as $groupName => $modules) {
            foreach ($modules as $module) {
                if (! $module['feature']) {
                    continue;
                }
                $grouped[$groupName][$module['feature']] = $module['label'] ?? $module['slug'];
            }
        }

        // Models que compartilham a mesma chave de módulo (ex.: Proposta
        // Comercial e Template de Proposta) se sobrescreviam: só o último
        // nome aparecia e o módulo "Propostas Comerciais" ficava invisível
        // na tela de Contratos. Mostra todos os nomes da chave juntos.
        foreach (static::labelsByFeature() as $feature => $labels) {
            foreach ($grouped as $groupName => $options) {
                if (isset($options[$feature]) && count($labels) > 1) {
                    $grouped[$groupName][$feature] = implode(' / ', $labels);
                }
            }
        }

        $grouped['Outros']['modulo_dashboard'] = 'Painel: Dashboard (Painel de Controle)';

        // modulo_configuracoes vai pro grupo "Configurações" de verdade (já
        // existe, é onde ficam Centro de Custo/Empresas/Filiais/Locais/Logs)
        // em vez de "Outros" -- usuário relatou 29/09/2026 que procurou e não
        // achou nessa seção, já que semanticamente é exatamente onde esse
        // toggle (liga a página App\Filament\Pages\ManageTenantSettings)
        // deveria estar.
        $grouped['Configurações']['modulo_configuracoes'] = 'Painel: Configurações do Tenant';
        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<string, array<int, string>> feature_key => nomes distintos dos módulos que a usam
     */
    protected static function labelsByFeature(): array
    {
        $map = [];
        foreach (SaaSRegistry::modules() as $module) {
            if ($module['feature']) {
                $map[$module['feature']][] = $module['label'] ?? $module['slug'];
            }
        }

        return array_map(fn ($labels) => array_values(array_unique($labels)), $map);
    }

    /**
     * Nome do campo do formulário pra um grupo (cada grupo tem seu próprio
     * CheckboxList, todos mesclados em 'features' antes de salvar -- ver
     * Pages\CreateContrato/EditContrato).
     */
    public static function groupFieldName(string $groupName): string
    {
        return 'features_group_'.Str::slug($groupName, '_');
    }

    /**
     * Total de módulos disponíveis pra marcar (soma de todos os grupos) --
     * fonte única pra "X de Y selecionados", usada tanto no resumo do
     * formulário quanto na coluna da listagem.
     */
    public static function totalAvailableModulesCount(): int
    {
        return collect(static::groupedFeatureOptions())->sum(fn ($options) => count($options));
    }

    public static function form(Form $form): Form
    {
        $groupedOptions = static::groupedFeatureOptions();

        $groupSections = [];
        foreach ($groupedOptions as $groupName => $options) {
            $groupSections[] = Forms\Components\Section::make($groupName)
                ->compact()
                ->collapsible()
                ->schema([
                    Forms\Components\CheckboxList::make(static::groupFieldName($groupName))
                        ->label('')
                        ->options($options)
                        ->bulkToggleable()
                        ->live()
                        ->columns(1),
                ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Contrato')
                ->description('Cada cliente tem o próprio conjunto de módulos e valor -- não existe mais plano padrão fechado.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Identificação do contrato')
                        ->placeholder('Ex: Nome do cliente ou da negociação')
                        ->helperText('Uso interno -- não aparece pro cliente, só ajuda você a reconhecer esse contrato depois.')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('base_price')
                        ->label('Valor Mensal')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),

                    Forms\Components\TextInput::make('implementation_fee')
                        ->label('Taxa de Implantação (cobrança única)')
                        ->helperText('Cobrada uma única vez, via Asaas, quando o cliente é cadastrado. Deixe vazio se não houver.')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$'),

                    Forms\Components\Select::make('implementation_installments')
                        ->label('Parcelas da Implantação')
                        ->options([1 => 'À vista (1x)', 2 => 'Em 2 vezes'])
                        ->default(1)
                        ->native(false),

                    Forms\Components\Select::make('implementation_billing_mode')
                        ->label('Como cobrar a Implantação')
                        ->options(['separada' => 'Cobrança separada (avulsa)', 'somada' => 'Somada à mensalidade'])
                        ->helperText('Somada: as parcelas vão dentro das primeiras mensalidades (ex.: 600 + 450, 600 + 450, depois só 600), numa única cobrança por mês.')
                        ->default('separada')
                        ->native(false),

                    Forms\Components\Select::make('payment_method')
                        ->label('Forma de pagamento da mensalidade')
                        ->options(['cartao' => 'Cartão de crédito (recorrente automático)', 'boleto_pix' => 'Boleto ou Pix todo mês (enviado por e-mail)'])
                        ->default('cartao')
                        ->native(false),

                    Forms\Components\Select::make('billing_cycle')
                        ->label('Ciclo de Cobrança')
                        ->options([
                            'monthly' => 'Mensal',
                            'quarterly' => 'Trimestral',
                            'semiannual' => 'Semestral',
                            'annual' => 'Anual',
                        ])
                        ->default('monthly')
                        ->required(),
                ]),

            Forms\Components\Section::make('Módulos incluídos')
                ->description('Selecione o que esse contrato específico libera pro cliente, agrupado pelos mesmos menus do painel.')
                ->schema([
                    // Resumo ao vivo (pedido do usuário: "que sejam
                    // contados em um resumo na tela logo ao lado de
                    // módulos incluidos por grupo, exemplo: Manutenção -
                    // OS, Avarias") -- recalcula a cada toggle porque os
                    // CheckboxList acima são ->live().
                    Forms\Components\Placeholder::make('modules_summary')
                        ->label('Resumo por grupo')
                        ->content(function (Get $get) use ($groupedOptions) {
                            $lines = [];
                            $totalSelected = 0;
                            $totalAvailable = static::totalAvailableModulesCount();

                            foreach ($groupedOptions as $groupName => $options) {
                                $selected = $get(static::groupFieldName($groupName)) ?? [];
                                $totalSelected += count($selected);

                                if (empty($selected)) {
                                    continue;
                                }

                                $labels = collect($selected)
                                    ->map(fn ($key) => $options[$key] ?? $key)
                                    ->sort()
                                    ->values();

                                $lines[] = $groupName.' ('.$labels->count().'): '.$labels->implode(', ');
                            }

                            // Total geral -- pedido do usuário 2026-09-24:
                            // "preciso do resumo total de quantos módulos
                            // estão selecionados na tela de Contratos". Antes
                            // só existia a quebra por grupo, sem nenhum
                            // número consolidado no topo.
                            $total = '<p class="mb-2 font-semibold text-gray-900 dark:text-white">'
                                .$totalSelected.' de '.$totalAvailable.' módulos selecionados</p>';

                            if (empty($lines)) {
                                return new HtmlString($total.'<span class="text-gray-500">Nenhum módulo selecionado ainda.</span>');
                            }

                            return new HtmlString(
                                $total
                                .'<ul class="list-disc list-inside space-y-1 text-sm">'
                                .collect($lines)->map(fn ($line) => '<li>'.e($line).'</li>')->implode('')
                                .'</ul>'
                            );
                        }),

                    Forms\Components\Grid::make(3)->schema($groupSections),
                ]),
        ]);
    }

    /**
     * Acha o Tenant que nasceu desse contrato (se algum cliente já chegou
     * a preencher o cadastro pelo link) e resume onde ele está no funil:
     * cadastro → contrato assinado → pago. Pedido do usuário 2026-09-23:
     * "uma forma de sabermos se foi assinado e pago". Pega o Tenant mais
     * recente com esse plan_id -- na prática cada Contrato vira o plano de
     * um cliente só, então não deveria haver ambiguidade real.
     *
     * @return array{tenant: ?Tenant, signature: ?DocumentSignature, label: string, color: string}
     */
    protected static function funnelStatusFor(Plan $plan): array
    {
        $tenant = Tenant::where('plan_id', $plan->id)->latest('created_at')->first();

        if (! $tenant) {
            return ['tenant' => null, 'signature' => null, 'label' => 'Aguardando cadastro', 'color' => 'gray'];
        }

        $signature = DocumentSignature::where('signable_type', Tenant::class)
            ->where('signable_id', $tenant->id)
            ->latest('created_at')
            ->first();

        if (! $signature || ! $signature->is_signed) {
            return ['tenant' => $tenant, 'signature' => $signature, 'label' => 'Aguardando assinatura do contrato', 'color' => 'warning'];
        }

        if ($tenant->asaas_payment_status === Tenant::PAYMENT_STATUS_EM_DIA) {
            return ['tenant' => $tenant, 'signature' => $signature, 'label' => 'Assinado e pago', 'color' => 'success'];
        }

        if ($tenant->asaas_payment_status === Tenant::PAYMENT_STATUS_CANCELADO) {
            return ['tenant' => $tenant, 'signature' => $signature, 'label' => 'Assinado, pagamento cancelado', 'color' => 'danger'];
        }

        return ['tenant' => $tenant, 'signature' => $signature, 'label' => 'Assinado, aguardando pagamento', 'color' => 'info'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Contrato')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('base_price')->label('Valor')->money('BRL')->weight('bold'),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Ciclo')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'quarterly' => 'Trimestral',
                        'semiannual' => 'Semestral',
                        'annual' => 'Anual',
                        default => 'Mensal',
                    }),
                Tables\Columns\TextColumn::make('features')
                    ->label('Módulos')
                    // Calculado pelo registro: o estado que o Filament entrega a esta coluna (array)
                    // chegava sem os booleanos e mostrava sempre "0 de N" (visto em PROD, 02/10/2026).
                    ->getStateUsing(function (Plan $record) {
                        $selected = collect($record->features ?? [])
                            ->filter(fn ($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
                            ->count();

                        return "{$selected} de ".static::totalAvailableModulesCount().' selecionado(s)';
                    }),
                // Status do funil: cadastro -> contrato assinado -> pago
                // (pedido do usuário 2026-09-23). Não é uma coluna real do
                // Plan -- ->state() calcula na hora, olhando o Tenant que
                // nasceu desse plan_id (se algum).
                Tables\Columns\TextColumn::make('funnel_status')
                    ->label('Assinado / Pago')
                    ->badge()
                    ->state(fn (Plan $record) => static::funnelStatusFor($record)['label'])
                    ->color(fn (Plan $record) => static::funnelStatusFor($record)['color']),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('copy_signup_link')
                    ->label('Copiar Link do Contrato')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->action(function (Plan $record) {
                        $link = route('checkout.create', ['plano' => $record->id]);

                        Notification::make()
                            ->title('Link do contrato')
                            ->body("Leva o cliente pelo fluxo completo: cadastro → assinar contrato → pagar.\n\n{$link}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                // Só aparece depois que um Tenant já nasceu desse link e
                // ainda não assinou -- pra reenviar SÓ a etapa de
                // assinatura, sem o cliente ter que preencher o cadastro
                // de novo (pedido do usuário: "antes do link da cobrança,
                // tenha link do contrato"). Depois de assinado, a própria
                // tela de assinatura não aceita reprocessar (ver
                // SignatureService::getSignatureByToken() -- can_sign exige
                // status 'pending'), então esconder faz sentido.
                Tables\Actions\Action::make('copy_contract_link')
                    ->label('Reenviar Link de Assinatura')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(fn (Plan $record) => (bool) (static::funnelStatusFor($record)['signature']?->is_signed === false))
                    ->action(function (Plan $record) {
                        $signature = static::funnelStatusFor($record)['signature'];
                        $link = route('signature.sign', ['token' => $signature->token]);

                        Notification::make()
                            ->title('Link de assinatura')
                            ->body("Só a etapa de assinatura, pro cliente que já se cadastrou:\n\n{$link}")
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContratos::route('/'),
            'create' => Pages\CreateContrato::route('/create'),
            'edit' => Pages\EditContrato::route('/{record}/edit'),
        ];
    }
}
