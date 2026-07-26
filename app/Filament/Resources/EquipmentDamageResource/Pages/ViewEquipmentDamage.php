<?php

namespace App\Filament\Resources\EquipmentDamageResource\Pages;

use App\Filament\Resources\EquipmentDamageResource;
use App\Filament\Resources\QuoteResource;
use App\Models\AIAnalysis;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\EquipmentReplacement;
use App\Models\Quote;
use App\Services\EquipmentDamageDiagnosisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipmentDamage extends ViewRecord
{
    protected static string $resource = EquipmentDamageResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Identificação')
                    ->schema([
                        Infolists\Components\TextEntry::make('maintenanceOrder.os_number')->label('OS'),
                        Infolists\Components\TextEntry::make('asset.patrimonio')->label('Patrimônio')->placeholder('—'),
                        Infolists\Components\TextEntry::make('asset.name')->label('Ativo'),
                        Infolists\Components\TextEntry::make('reportedBy.name')->label('Reportado por'),
                        Infolists\Components\TextEntry::make('created_at')->label('Data do registro')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Diagnóstico do Técnico')
                    ->schema([
                        Infolists\Components\TextEntry::make('severity')
                            ->label('Severidade')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => match ($state) {
                                EquipmentDamage::SEVERITY_LEVE => 'Leve',
                                EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                                EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                                default => $state,
                            }),
                        Infolists\Components\IconEntry::make('requires_replacement')->label('Exige troca?')->boolean(),
                        Infolists\Components\TextEntry::make('cause')
                            ->label('Causa')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => EquipmentDamage::causeLabels()[$state] ?? 'Não classificado')
                            ->color(fn (?string $state) => match ($state) {
                                EquipmentDamage::CAUSE_MAU_USO, EquipmentDamage::CAUSE_DANO_CLIENTE => 'danger',
                                EquipmentDamage::CAUSE_DESGASTE_NATURAL => 'success',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('description')->label('Descrição')->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Evidências Fotográficas')
                    ->schema([
                        Infolists\Components\ViewEntry::make('photos')
                            ->label('')
                            ->view('filament.infolists.equipment-damage-photos'),
                    ]),

                Infolists\Components\Section::make('Diagnóstico por IA')
                    ->description('Sugestão gerada por IA a partir dos dados desta avaria — sempre confira antes de usar.')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        Infolists\Components\TextEntry::make('latestAiAnalysis.response.diagnostico_provavel')
                            ->label('Diagnóstico provável')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('latestAiAnalysis.response.causa_raiz')
                            ->label('Causa raiz'),
                        Infolists\Components\TextEntry::make('latestAiAnalysis.response.tempo_estimado_reparo')
                            ->label('Tempo estimado de reparo'),
                        Infolists\Components\TextEntry::make('latestAiAnalysis.response.acoes_corretivas')
                            ->label('Ações corretivas')
                            ->listWithLineBreaks()
                            ->bulleted(),
                        Infolists\Components\TextEntry::make('latestAiAnalysis.response.pecas_necessarias')
                            ->label('Peças necessárias')
                            ->listWithLineBreaks()
                            ->bulleted(),
                        Infolists\Components\TextEntry::make('custo_estimado_ia')
                            ->label('Custo estimado')
                            ->state(function ($record) {
                                $r = $record->latestAiAnalysis?->response;
                                if (! $r || (blank($r['custo_estimado_min'] ?? null) && blank($r['custo_estimado_max'] ?? null))) {
                                    return null;
                                }

                                $min = number_format((float) ($r['custo_estimado_min'] ?? 0), 2, ',', '.');
                                $max = number_format((float) ($r['custo_estimado_max'] ?? 0), 2, ',', '.');

                                return "R$ {$min} a R$ {$max}";
                            }),
                        Infolists\Components\TextEntry::make('latestAiAnalysis.response.equipamento_substituto_sugerido')
                            ->label('Substituto sugerido')
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->latestAiAnalysis?->status === AIAnalysis::STATUS_CONCLUIDA),

                Infolists\Components\Section::make('Ciência do Cliente')
                    ->schema([
                        Infolists\Components\ViewEntry::make('client_signature')
                            ->label('Assinatura')
                            ->view('filament.infolists.signature-image'),
                        Infolists\Components\TextEntry::make('client_acknowledged_at')->label('Assinado em')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->client_acknowledged_at !== null),

                Infolists\Components\Section::make('Revisão do Supervisor')
                    ->schema([
                        Infolists\Components\TextEntry::make('supervisor_notes')->label('Observações do supervisor')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('supervisorReviewedBy.name')->label('Revisado por'),
                        Infolists\Components\TextEntry::make('supervisor_reviewed_at')->label('Revisado em')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->supervisor_notes || $record->supervisor_reviewed_at),

                Infolists\Components\Section::make('Orçamento Indenizatório')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('quotes')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state) => Quote::typeLabels()[$state] ?? $state)
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state) => Quote::statusLabels()[$state] ?? $state)
                                    ->color(fn (string $state) => match ($state) {
                                        Quote::STATUS_ENVIADO => 'info',
                                        Quote::STATUS_APROVADO => 'success',
                                        Quote::STATUS_REPROVADO => 'danger',
                                        Quote::STATUS_CONCLUIDO => 'primary',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('total_value')
                                    ->label('Valor')
                                    ->money('BRL'),
                                Infolists\Components\TextEntry::make('id')
                                    ->label('')
                                    ->formatStateUsing(fn () => 'Abrir orçamento →')
                                    ->color('primary')
                                    ->url(fn (Quote $record) => QuoteResource::getUrl('edit', ['record' => $record])),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn ($record) => $record->quotes()->exists()),

                Infolists\Components\Section::make('Tratativa Comercial')
                    ->schema([
                        Infolists\Components\TextEntry::make('estimated_cost')
                            ->label('Valor estimado')
                            ->money('BRL'),
                        Infolists\Components\TextEntry::make('replacementAsset.patrimonio')
                            ->label('Patrimônio do substituto')
                            ->placeholder('Nenhum vinculado ainda'),
                        Infolists\Components\TextEntry::make('replacementAsset.name')
                            ->label('Ativo substituto vinculado')
                            ->placeholder('Nenhum vinculado ainda'),
                        Infolists\Components\TextEntry::make('commercialReviewedBy.name')->label('Tratado por'),
                        Infolists\Components\TextEntry::make('commercial_reviewed_at')->label('Em')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->commercial_reviewed_at !== null || $record->replacement_asset_id !== null),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analisar_ia')
                ->label('Analisar com IA')
                ->color('gray')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn () => (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias')
                    && ! in_array($this->record->status, [EquipmentDamage::STATUS_RESOLVIDO, EquipmentDamage::STATUS_CANCELADO], true))
                ->requiresConfirmation()
                ->modalHeading('Analisar avaria com IA?')
                ->modalDescription('Envia os dados desta avaria (incluindo fotos) para análise por IA. A sugestão é só um apoio — nada é alterado automaticamente.')
                ->modalSubmitActionLabel('Analisar')
                ->action(function (EquipmentDamageDiagnosisService $service): void {
                    $analysis = $service->analyze($this->record, auth()->id());

                    if ($analysis->status === AIAnalysis::STATUS_CONCLUIDA) {
                        Notification::make()
                            ->title('Análise concluída')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Não foi possível concluir a análise')
                            ->body($analysis->error)
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('baixar_laudo')
                ->label('Baixar Laudo Jurídico (PDF)')
                ->color('gray')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn () => route('equipment-damages.laudo.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('confirmar')
                ->label('Confirmar / Ajustar Diagnóstico')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Select::make('severity')
                        ->label('Severidade')
                        ->options([
                            EquipmentDamage::SEVERITY_LEVE => 'Leve',
                            EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
                            EquipmentDamage::SEVERITY_GRAVE => 'Grave / Perda Total',
                        ])
                        ->default(fn () => $this->record->severity)
                        ->required(),
                    Forms\Components\Toggle::make('requires_replacement')
                        ->label('Exige substituição do equipamento?')
                        ->default(fn () => $this->record->requires_replacement),
                    Forms\Components\Select::make('cause')
                        ->label('Causa')
                        ->options(EquipmentDamage::causeLabels())
                        ->default(fn () => $this->record->cause)
                        ->placeholder('Não classificado')
                        ->helperText('Confirme ou ajuste a causa antes de encaminhar ao Comercial.'),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'severity' => $data['severity'],
                        'requires_replacement' => $data['requires_replacement'],
                        'cause' => $data['cause'] ?? null,
                        'status' => EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL,
                        'supervisor_reviewed_by' => auth()->id(),
                        'supervisor_reviewed_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Diagnóstico confirmado')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('pedir_info')
                ->label('Pedir Mais Informação')
                ->color('warning')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('O que precisa ser esclarecido?')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['supervisor_notes' => $data['note']]);

                    if ($technician = $this->record->reportedBy) {
                        Notification::make()
                            ->title('Supervisor pediu mais informações sobre uma avaria')
                            ->body($data['note'])
                            ->warning()
                            ->sendToDatabase($technician);
                    }

                    Notification::make()
                        ->title('Pedido de informação enviado ao técnico')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('iniciar_cobranca')
                ->label('Iniciar Cobrança')
                ->color('success')
                ->icon('heroicon-o-currency-dollar')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\TextInput::make('estimated_cost')
                        ->label('Valor estimado da cobrança (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'estimated_cost' => $data['estimated_cost'],
                        'status' => EquipmentDamage::STATUS_EM_COBRANCA,
                        'commercial_reviewed_by' => auth()->id(),
                        'commercial_reviewed_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Cobrança iniciada')
                        ->success()
                        ->send();
                }),

            // POP 5/6: quando a causa responsabiliza o cliente (mau_uso ou
            // dano_cliente), o Comercial gera um orçamento indenizatório de
            // verdade (peças via Almoxarifado + serviços) em vez de só
            // digitar um valor solto em estimated_cost.
            Actions\Action::make('gerar_orcamento_indenizatorio')
                ->label('Gerar Orçamento Indenizatório')
                ->color('primary')
                ->icon('heroicon-o-document-plus')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL
                    && $this->record->isBillableToClient()
                    && $this->record->quotes()->doesntExist()
                    && auth()->user()->can('update', $this->record))
                ->requiresConfirmation()
                ->modalHeading('Gerar Orçamento Indenizatório?')
                ->modalDescription('Cria um orçamento vinculado a esta avaria para cobrar o cliente. Você poderá adicionar os itens (peças e serviços) em seguida.')
                ->action(function () {
                    if (! $this->record->asset?->client_id) {
                        Notification::make()
                            ->title('Não foi possível gerar o orçamento')
                            ->body('O ativo desta avaria não tem um cliente vinculado.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $quote = Quote::create([
                        'quotable_type' => EquipmentDamage::class,
                        'quotable_id' => $this->record->id,
                        'client_id' => $this->record->asset->client_id,
                        'assigned_user_id' => auth()->id(),
                        'type' => Quote::TYPE_INDENIZATORIO,
                    ]);

                    Notification::make()
                        ->title('Orçamento indenizatório criado')
                        ->body('Adicione os itens (peças e serviços) para enviar ao cliente.')
                        ->success()
                        ->send();

                    $this->redirect(QuoteResource::getUrl('edit', ['record' => $quote]));
                }),

            Actions\Action::make('vincular_substituto')
                ->label('Vincular Ativo Substituto')
                ->color('info')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL
                    && $this->record->requires_replacement
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Select::make('replacement_asset_id')
                        ->label('Ativo substituto')
                        ->options(fn () => Asset::query()
                            ->where('asset_category', $this->record->asset?->asset_category)
                            ->where('id', '!=', $this->record->asset_id)
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['replacement_asset_id' => $data['replacement_asset_id']]);

                    // Ate 2026-07-17 isso so' setava EquipmentDamage.replacement_asset_id
                    // -- nao existia nenhum EquipmentReplacement de verdade ligado, entao
                    // o workflow de troca (identificar/desmobilizar/mobilizar) nunca era
                    // disparado a partir de uma Avaria. firstOrNew por equipment_damage_id
                    // evita duplicar se a acao for repetida (troca de substituto).
                    $replacement = EquipmentReplacement::firstOrNew(['equipment_damage_id' => $this->record->id]);
                    $replacement->fill([
                        'tenant_id' => $this->record->tenant_id,
                        'maintenance_order_id' => $this->record->maintenance_order_id,
                        'equipment_damage_id' => $this->record->id,
                        'original_asset_id' => $this->record->asset_id,
                        'requested_by_user_id' => $replacement->requested_by_user_id ?? auth()->id(),
                        'reason' => $replacement->reason ?: ('Avaria: '.$this->record->description),
                    ]);
                    $replacement->save();
                    $replacement->identifyReplacement(Asset::find($data['replacement_asset_id']), auth()->user());

                    Notification::make()
                        ->title('Ativo substituto vinculado')
                        ->body('Troca de equipamento criada e substituto já identificado.')
                        ->success()
                        ->send();
                }),

            // Ate 2026-07-14 resolvido/cancelado existiam como constante e
            // como opcao de filtro na listagem, mas nenhuma acao de tela
            // levava uma Avaria ate esses estados -- ficava sem fechamento
            // formal depois de em_cobranca.
            Actions\Action::make('marcar_resolvida')
                ->label('Marcar como Resolvida')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->modalHeading('Marcar Avaria como Resolvida?')
                ->visible(fn () => $this->record->status === EquipmentDamage::STATUS_EM_COBRANCA
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('Observação final (opcional)'),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => EquipmentDamage::STATUS_RESOLVIDO,
                        'supervisor_notes' => $data['note'] ?: $this->record->supervisor_notes,
                    ]);

                    Notification::make()
                        ->title('Avaria marcada como resolvida')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('cancelar_avaria')
                ->label('Cancelar Avaria')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Cancelar esta Avaria?')
                ->modalDescription('Use quando a avaria reportada não procede (falso positivo).')
                ->visible(fn () => ! in_array($this->record->status, [EquipmentDamage::STATUS_RESOLVIDO, EquipmentDamage::STATUS_CANCELADO], true)
                    && auth()->user()->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo do cancelamento')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => EquipmentDamage::STATUS_CANCELADO,
                        'supervisor_notes' => $data['reason'],
                    ]);

                    Notification::make()
                        ->title('Avaria cancelada')
                        ->warning()
                        ->send();
                }),
        ];
    }
}
