<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\ContractResource\Pages;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentReplacement;
use App\Services\CepGeocodingService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ContractResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Comercial / Contratos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('1. Qualificação das Partes')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ do Cliente')
                            ->mask('99.999.999/9999-99')
                            ->required(),
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->label('Cliente')
                            ->required()
                            ->columnSpan(2),
                    ]),
                ]),

            Forms\Components\Section::make('2. Objeto e Prazos')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('contract_number')
                            ->label('Número do Contrato')
                            ->required(),
                        Forms\Components\Select::make('asset_id')
                            ->relationship('asset', 'name')
                            ->label('Equipamento (Marca/Série)')
                            ->required()
                            ->live()
                            // Bloqueio automatico por PMP critico vencido (pedido
                            // do usuario 2026-08-27, CheckMaintenanceDueAlerts
                            // marca Asset.blocked_by_pmp_at) -- so' impede
                            // CRIAR contrato novo pro ativo bloqueado, nao afeta
                            // edicao de um contrato ja existente pro mesmo ativo.
                            ->rule(function (?Contract $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($record) {
                                    if ($record) {
                                        return;
                                    }

                                    $asset = Asset::find($value);
                                    if ($asset?->blocked_by_pmp_at) {
                                        $fail('Equipamento com manutenção preventiva crítica vencida — regularize antes de locar.');
                                    }
                                };
                            }),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Início da Vigência')
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Fim da Vigência')
                            ->live()
                            ->required(fn (Get $get) => $get('billing_type') === Contract::BILLING_DIARIA),
                    ]),
                ]),

            Forms\Components\Section::make('Local de Instalação do Equipamento')
                ->description('Onde o equipamento fica fisicamente instalado durante a locação — a Ordem de Serviço e o Dossiê do ativo puxam esta localização automaticamente.')
                ->columns(3)
                ->schema([
                    Forms\Components\Radio::make('local_tipo')
                        ->label('Local de Instalação')
                        ->options(Contract::localTipoOptions())
                        ->default(Contract::LOCAL_TIPO_SEDE_EMPRESA)
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('internal_unit_id')
                        ->label('Unidade/Filial')
                        ->relationship('internalUnit', 'name')
                        ->visible(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_SEDE_EMPRESA)
                        ->required(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_SEDE_EMPRESA)
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('endereco_cliente_display')
                        ->label('Endereço do Cliente (já cadastrado)')
                        ->visible(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_ENDERECO_CLIENTE)
                        ->content(function (Get $get) {
                            $client = $get('client_id') ? Client::find($get('client_id')) : null;

                            if (! $client || ! $client->address) {
                                return new HtmlString('<span class="text-gray-400">Selecione um cliente com endereço cadastrado.</span>');
                            }

                            return new HtmlString(e("{$client->address}, {$client->city} - {$client->uf}, CEP {$client->cep}"));
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('cep_obra')
                        ->label('CEP')
                        ->placeholder('00000-000')
                        ->live(onBlur: true)
                        ->visible(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_OUTRO)
                        ->required(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_OUTRO)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $service = app(CepGeocodingService::class);
                            $endereco = $service->lookupCep($state);

                            if (! $endereco) {
                                Notification::make()->title('CEP não encontrado.')->warning()->send();

                                return;
                            }

                            $set('endereco_obra', $endereco['address']);
                            $set('cidade_obra', $endereco['city']);
                            $set('uf_obra', $endereco['uf']);

                            $fullAddress = trim($endereco['address'].', '.$endereco['city'].' - '.$endereco['uf']);
                            $coords = $service->geocodeAddress($fullAddress);

                            if ($coords) {
                                $set('latitude_obra', $coords['latitude']);
                                $set('longitude_obra', $coords['longitude']);
                                Notification::make()->title('Endereço localizado no mapa.')->success()->send();
                            } else {
                                $set('latitude_obra', null);
                                $set('longitude_obra', null);
                                Notification::make()->title('Endereço preenchido, mas não foi possível localizar no mapa automaticamente.')->warning()->send();
                            }
                        }),
                    Forms\Components\TextInput::make('cidade_obra')
                        ->label('Cidade')
                        ->visible(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_OUTRO),
                    Forms\Components\TextInput::make('uf_obra')
                        ->label('UF')
                        ->maxLength(2)
                        ->visible(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_OUTRO),
                    Forms\Components\TextInput::make('endereco_obra')
                        ->label('Endereço')
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_OUTRO)
                        ->required(fn (Get $get) => $get('local_tipo') === Contract::LOCAL_TIPO_OUTRO),
                    Forms\Components\Hidden::make('latitude_obra'),
                    Forms\Components\Hidden::make('longitude_obra'),

                    Forms\Components\Select::make('condicao_ambiente')
                        ->label('Condições do Ambiente')
                        ->options(Contract::condicaoOptions())
                        ->required()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('3. Manutenção, Logística e Riscos')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('responsavel_manutencao')
                            ->options(['locador' => 'Locadora', 'locatario' => 'Locatário'])
                            ->label('Responsável pela Manutenção')
                            ->required(),
                        Forms\Components\Toggle::make('frete_incluso')
                            ->label('Frete de Ida/Volta incluso?'),
                    ]),
                    Forms\Components\RichEditor::make('regras_uso')
                        ->label('Normas de Uso e Segurança (NRs)')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('4. Responsabilidades e Rescisão')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('billing_type')
                            ->label('Modalidade de Cobrança')
                            ->options(Contract::billingTypeOptions())
                            ->default(Contract::BILLING_MENSAL_FIXO)
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('multa_rescisoria')
                            ->label('Multa Rescisória (%)')
                            ->numeric(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('price')
                            ->label(fn (Get $get) => $get('billing_type') === Contract::BILLING_DIARIA ? 'Valor da Diária' : 'Valor Mensal')
                            ->prefix('R$')
                            ->numeric()
                            ->live()
                            ->required(),
                        Forms\Components\Placeholder::make('total_diaria_calculado')
                            ->label('Valor Total do Contrato (calculado)')
                            ->visible(fn (Get $get) => $get('billing_type') === Contract::BILLING_DIARIA)
                            ->content(function (Get $get) {
                                $start = $get('start_date');
                                $end = $get('end_date');
                                $price = (float) ($get('price') ?? 0);

                                if (! $start || ! $end) {
                                    return new HtmlString('<span class="text-gray-400">Preencha início e fim da vigência.</span>');
                                }

                                $dias = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

                                if ($dias <= 0) {
                                    return new HtmlString('<span class="text-danger-500">Data de fim deve ser após o início.</span>');
                                }

                                $total = $dias * $price;

                                return new HtmlString(sprintf(
                                    '%d dia(s) x R$ %s = <strong>R$ %s</strong>',
                                    $dias,
                                    number_format($price, 2, ',', '.'),
                                    number_format($total, 2, ',', '.')
                                ));
                            }),
                    ]),
                    Forms\Components\FileUpload::make('vistoria_entrega')
                        ->label('Laudo de Vistoria de Entrega (PDF/Imagem)')
                        ->directory('contratos/vistorias')
                        ->downloadable()
                        ->previewable(),
                ]),

            Forms\Components\Section::make('5. Histórico de Substituição de Equipamento')
                ->description('Equipamentos locados neste contrato e, quando houve troca, qual equipamento entrou, quando e por quê.')
                ->schema([
                    Forms\Components\Placeholder::make('equipment_replacement_history')
                        ->label('')
                        ->content(fn (?Contract $record) => static::renderReplacementHistory($record))
                        ->columnSpanFull(),
                ])
                ->visible(fn (?Contract $record) => $record !== null),
        ]);
    }

    /**
     * Historico de troca de equipamento deste contrato: qual ativo saiu,
     * qual entrou, o diagnostico (motivo), a OS/patrimonio/tecnico que
     * atestou e as datas -- mesma informacao que tambem aparece no
     * historico do proprio Ativo (AssetResource), aqui filtrada por
     * contrato. EquipmentReplacement::contract_id e resolvido
     * automaticamente na criacao (ver EquipmentReplacement::booted()).
     */
    private static function renderReplacementHistory(?Contract $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
        }

        $replacements = $record->equipmentReplacements()
            ->with(['originalAsset', 'replacementAsset', 'maintenanceOrder', 'requestedBy', 'mobilizationMovement'])
            ->latest()
            ->get();

        if ($replacements->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">Nenhuma troca de equipamento registrada para este contrato.</span>');
        }

        $statusLabels = [
            EquipmentReplacement::STATUS_SOLICITADO => ['Solicitado', 'gray'],
            EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO => ['Substituto Identificado', 'info'],
            EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO => ['Desmobilização em Andamento', 'warning'],
            EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO => ['Mobilização em Andamento', 'warning'],
            EquipmentReplacement::STATUS_CONCLUIDO => ['Concluído', 'success'],
            EquipmentReplacement::STATUS_CANCELADO => ['Cancelado', 'danger'],
        ];
        $badgeColors = [
            'gray' => '#6b7280', 'info' => '#0ea5e9', 'warning' => '#d97706',
            'success' => '#16a34a', 'danger' => '#dc2626',
        ];

        $rows = $replacements->map(function (EquipmentReplacement $r) use ($statusLabels, $badgeColors) {
            [$statusLabel, $statusColor] = $statusLabels[$r->status] ?? [$r->status, 'gray'];
            $color = $badgeColors[$statusColor];
            $original = $r->originalAsset;
            $replacement = $r->replacementAsset;
            $os = $r->maintenanceOrder;
            $deliveredAt = $r->delivered_at?->format('d/m/Y H:i');

            $equipmentCell = e($original?->name ?? '—').' ('.e($original?->patrimonio ?? 's/ patrimônio').')'
                .($replacement
                    ? ' → '.e($replacement->name).' ('.e($replacement->patrimonio ?? 's/ patrimônio').')'
                    : ' <span style="color:#9ca3af">(substituto ainda não definido)</span>');

            return "<tr style='border-top:1px solid rgba(156,163,175,0.2)'>".
                "<td style='padding:8px 12px'>{$equipmentCell}</td>".
                "<td style='padding:8px 12px'>".e($r->reason).'</td>'.
                "<td style='padding:8px 12px'>".e($os?->os_number ?? '—').'</td>'.
                "<td style='padding:8px 12px'>".e($r->requestedBy?->name ?? '—').'</td>'.
                "<td style='padding:8px 12px'><span style='color:{$color};font-weight:600'>{$statusLabel}</span></td>".
                "<td style='padding:8px 12px'>".e($deliveredAt ?? '—').'</td>'.
                '</tr>';
        })->implode('');

        return new HtmlString(
            "<div style='overflow-x:auto'><table style='width:100%;font-size:13px;border-collapse:collapse'>".
            '<thead><tr style="text-align:left;color:#9ca3af">'.
            '<th style="padding:8px 12px">Equipamento (original → substituto)</th>'.
            '<th style="padding:8px 12px">Diagnóstico</th>'.
            '<th style="padding:8px 12px">OS</th>'.
            '<th style="padding:8px 12px">Técnico</th>'.
            '<th style="padding:8px 12px">Status</th>'.
            '<th style="padding:8px 12px">Entrega ao Cliente</th>'.
            "</tr></thead><tbody>{$rows}</tbody></table></div>"
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),
                Tables\Columns\TextColumn::make('contract_number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente'),
                Tables\Columns\TextColumn::make('asset.patrimonio')->label('Patrimônio'),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo'),
                Tables\Columns\TextColumn::make('start_date')->date('d/m/Y'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Ativo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Rascunho',
                        'Ativo' => 'Ativo',
                        'Encerrado' => 'Encerrado',
                    ]),
                Tables\Filters\Filter::make('vence_em_30_dias')
                    ->label('Vence em 30 dias')
                    ->query(fn ($query) => $query->where('status', 'Ativo')
                        ->whereNotNull('end_date')
                        ->whereBetween('end_date', [now(), now()->addDays(30)]))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
