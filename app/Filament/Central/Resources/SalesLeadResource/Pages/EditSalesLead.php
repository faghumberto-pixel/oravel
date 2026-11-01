<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ContentTabPosition;
use Filament\Resources\Pages\EditRecord;

class EditSalesLead extends EditRecord
{
    protected static string $resource = SalesLeadResource::class;

    // Uma barra de abas so': Dados | Histórico | Contatos | Agenda (em vez do form seguido de abas embaixo).
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Dados';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-identification';
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return ContentTabPosition::Before;
    }

    /** Resumo no topo: etapa, como falar e quando foi o ultimo contato / quando voltar. */
    public function getSubheading(): ?string
    {
        /** @var SalesLead $l */
        $l = $this->getRecord();

        return collect([
            'Etapa: '.(SalesLead::stageLabels()[$l->pipeline_stage] ?? $l->pipeline_stage),
            $l->phone,
            $l->email,
            $l->last_interaction_at ? 'Último contato '.$l->last_interaction_at->format('d/m/Y') : 'Nunca contatado',
            $l->next_followup_date ? 'Retornar em '.$l->next_followup_date->format('d/m/Y') : null,
        ])->filter()->implode('  ·  ');
    }

    /**
     * Mesmas 3 acoes gated da tabela (SalesLeadResource::table()), aqui
     * tambem -- quem abre o lead pelo card do Funil de Vendas precisa
     * das mesmas acoes, nao so' do form de edicao.
     */
    protected function getHeaderActions(): array
    {
        /** @var SalesLead $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('registrar_contato')
                ->label('Registrar contato')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->visible(fn () => $record->isOpen())
                ->form([
                    Forms\Components\Select::make('channel')->label('Canal')->options(SalesLeadInteraction::channelLabels())->default(SalesLeadInteraction::CHANNEL_WHATSAPP)->required()->native(false),
                    Forms\Components\DateTimePicker::make('contact_date')->label('Quando')->default(now())->required()->native(false)->displayFormat('d/m/Y H:i'),
                    Forms\Components\Textarea::make('summary')->label('O que foi dito / combinado')->required()->rows(4),
                    Forms\Components\DatePicker::make('next_followup_date')->label('Próximo retorno (opcional)')->native(false)->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data) use ($record) {
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
                    $this->refreshFormData(['next_followup_date']);
                }),
            Actions\Action::make('advance')
                ->label('Avançar Estágio')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('gray')
                ->visible(fn () => $record->isOpen() && $record->nextStage() && $record->nextStage() !== SalesLead::STAGE_GANHO)
                ->action(function () use ($record) {
                    try {
                        $record->advanceStage();
                        Notification::make()->title('Estágio avançado.')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível avançar')->body($e->getMessage())->warning()->send();
                    }
                }),
            Actions\Action::make('convert')
                ->label('Converter em Tenant')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $record->pipeline_stage === SalesLead::STAGE_PROPOSTA_ENVIADA)
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
                ->mountUsing(fn (Forms\Form $form) => $form->fill([
                    'admin_name' => $record->primaryDecisionMaker()['name'] ?? null,
                    'admin_email' => $record->email,
                ]))
                ->action(function (array $data) use ($record) {
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
            Actions\Action::make('mark_lost')
                ->label('Marcar como Perdido')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $record->isOpen())
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Select::make('lost_reason')
                        ->label('Motivo')
                        ->options(SalesLead::lostReasonLabels())
                        ->required(),
                    Forms\Components\Textarea::make('lost_reason_detail')
                        ->label('Detalhe'),
                ])
                ->action(fn (array $data) => $record->markLost($data['lost_reason'], $data['lost_reason_detail'] ?? null)),
            Actions\DeleteAction::make(),
        ];
    }
}
