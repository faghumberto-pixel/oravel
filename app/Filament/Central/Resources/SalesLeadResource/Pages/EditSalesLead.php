<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\Plan;
use App\Models\SalesLead;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSalesLead extends EditRecord
{
    protected static string $resource = SalesLeadResource::class;

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
