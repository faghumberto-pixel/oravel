<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Pages\DossieOperacional;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\MaintenanceOrderResource\Concerns\CreatesDamageFromAvariaType;
use App\Filament\Resources\MaintenanceOrderResource\Concerns\StoresPhotoEvidence;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('maintenance')]
class EditMaintenanceOrder extends EditRecord
{
    use CreatesDamageFromAvariaType;
    use StoresPhotoEvidence;

    protected static string $resource = MaintenanceOrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillPhotoEvidences($this->getRecord(), $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->extractPhotoEvidences($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistPhotoEvidences($this->record);
        $this->createDamageFromAvariaType($this->record);
    }

    /**
     * So' as 3 acoes do dia a dia (Iniciar/Pausar/Concluir) ficam soltas no
     * cabecalho -- o resto (Dossie, Preventiva, Reprogramar, Transferir,
     * Cancelar, Excluir) vai pro dropdown "Mais Acoes". Com as 10 acoes
     * antigas soltas, o cabecalho da tela de OS virava uma parede de botoes
     * quebrando linha sem nenhum criterio no celular -- ver reclamacao do
     * usuario sobre o layout mobile, 2026-07-14.
     */
    protected function getHeaderActions(): array
    {
        return [
            // 1. BOTÃO INICIAR / CONTINUAR
            // O timer (last_timer_start/started_at/total_time_seconds/finished_at) é
            // responsabilidade única do hook MaintenanceOrder::booted() -- as actions
            // abaixo só mudam 'status' (+ campos próprios da transição) e deixam o
            // hook recalcular o tempo. Antes disso, o timer era recalculado aqui E
            // no hook a cada transição -- funcionava por coincidência de ordem de
            // execução, mas era frágil pra qualquer mudança futura.
            Actions\Action::make('iniciar')
                ->label(fn () => $this->record->started_at ? 'Continuar Serviço' : 'Iniciar Serviço')
                ->color('success')
                ->icon('heroicon-o-play')
                ->disabled(fn () => ! in_array($this->record->status, ['Aberto', 'Pendente', 'Pausada', 'Reprogramado', 'Reprogramada']))
                ->action(function () {
                    $oldStatus = $this->record->status;
                    $this->record->update(['status' => 'Em Andamento']);
                    $this->record->logStatusChange('Em Andamento', $oldStatus);

                    Notification::make()
                        ->title('Serviço iniciado!')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'started_at']);
                }),

            // 2. BOTÃO PAUSAR
            Actions\Action::make('pausar')
                ->label('Pausar')
                ->color('warning')
                ->icon('heroicon-o-pause')
                ->disabled(fn () => $this->record->status !== 'Em Andamento')
                ->action(function () {
                    $oldStatus = $this->record->status;
                    $this->record->update(['status' => 'Pausada']);
                    $this->record->logStatusChange('Pausada', $oldStatus);

                    Notification::make()
                        ->title('Serviço pausado')
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            // 3. BOTÃO CONCLUIR
            Actions\Action::make('concluir')
                ->label('Concluir')
                ->color('danger')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Concluir Ordem de Serviço?')
                ->modalDescription('O tempo será totalizado e a OS será finalizada para faturamento/fechamento.')
                ->disabled(fn () => ! in_array($this->record->status, ['Em Andamento', 'Pausada']))
                ->action(function () {
                    $oldStatus = $this->record->status;
                    $this->record->update(['status' => 'Concluída']);
                    $this->record->logStatusChange('Concluída', $oldStatus);

                    Notification::make()
                        ->title('Ordem de Serviço Concluída!')
                        ->success()
                        ->persistent()
                        ->send();

                    $this->refreshFormData(['status', 'finished_at']);
                }),

            // 4. MAIS AÇÕES (agrupa tudo que não é do dia a dia num dropdown --
            // consultas/impressão primeiro, depois transições administrativas,
            // destrutivas por último)
            Actions\ActionGroup::make([
                Actions\Action::make('dossie')
                    ->label('Dossiê Operacional')
                    ->icon('heroicon-o-document-chart-bar')
                    ->url(fn () => DossieOperacional::getUrl(['record' => $this->record]))
                    ->openUrlInNewTab(),

                // Preventiva (celular) so aparece se o ativo tiver Grupo
                // definido, ja que o template de preventiva e por Grupo.
                Actions\Action::make('preventiva')
                    ->label('Preventiva (Celular)')
                    ->icon('heroicon-o-wrench')
                    ->visible(fn () => (bool) $this->record->asset?->checklist_group_id)
                    ->url(fn () => route('maintenance-orders.preventiva-mobile', $this->record))
                    ->openUrlInNewTab(),

                Actions\Action::make('preventiva_print')
                    ->label('Imprimir Preventiva')
                    ->icon('heroicon-o-printer')
                    ->visible(fn () => (bool) $this->record->asset?->checklist_group_id)
                    ->url(fn () => route('maintenance-orders.preventiva.print', $this->record))
                    ->openUrlInNewTab(),

                // Checklist mobile de mobilizacao/desmobilizacao (EquipmentMovementMobile)
                // -- so' faz sentido pra O.S. de Check-in/Check-out (troca fisica do
                // ativo), nao pra manutencao corretiva/preventiva comum. Ate 2026-07-14
                // essa rota funcionava mas nao tinha NENHUM link pra ela na tela de OS.
                Actions\Action::make('abrir_checklist_movimentacao')
                    ->label(fn () => $this->record->maintenance_type === MaintenanceOrder::TYPE_CHECKOUT
                        ? 'Abrir Checklist de Desmobilização'
                        : 'Abrir Checklist de Mobilização')
                    ->icon('heroicon-o-truck')
                    ->visible(fn () => in_array($this->record->maintenance_type, [MaintenanceOrder::TYPE_CHECKIN, MaintenanceOrder::TYPE_CHECKOUT], true))
                    ->url(fn () => route('maintenance-orders.equipment-movement-mobile', [
                        'maintenanceOrder' => $this->record,
                        'type' => $this->record->maintenance_type === MaintenanceOrder::TYPE_CHECKOUT
                            ? EquipmentMovement::TYPE_DESMOBILIZACAO
                            : EquipmentMovement::TYPE_MOBILIZACAO,
                    ]))
                    ->openUrlInNewTab(),

                Actions\Action::make('reprogramar')
                    ->label('Reprogramar')
                    ->icon('heroicon-o-calendar')
                    ->disabled(fn () => ! in_array($this->record->status, ['Aberto', 'Pendente', 'Pausada', 'Em Andamento']))
                    ->form([
                        DateTimePicker::make('rescheduled_to')
                            ->label('Nova Data e Hora')
                            ->required()
                            ->minDate(now())
                            ->displayFormat('d/m/Y H:i'),
                        Textarea::make('reschedule_reason')
                            ->label('Motivo da Reprogramação')
                            ->required()
                            ->placeholder('Justifique a alteração de data...'),
                    ])
                    ->action(function (array $data) {
                        $oldStatus = $this->record->status;

                        $this->record->update([
                            'status' => 'Reprogramado',
                            'rescheduled_to' => $data['rescheduled_to'],
                            'reschedule_reason' => $data['reschedule_reason'],
                        ]);
                        $this->record->logStatusChange('Reprogramado', $oldStatus, $data['reschedule_reason']);

                        Notification::make()
                            ->title('OS Reprogramada')
                            ->info()
                            ->send();

                        $this->refreshFormData(['status', 'rescheduled_to', 'reschedule_reason']);
                    }),

                // BOTÃO TRANSFERIR (Ajustado para Multi-tenancy)
                Actions\Action::make('transferir')
                    ->label('Transferir')
                    ->icon('heroicon-o-arrows-right-left')
                    ->disabled(fn () => ! in_array($this->record->status, ['Aberto', 'Pendente', 'Pausada', 'Reprogramado', 'Reprogramada', 'Em Andamento']))
                    ->form([
                        Select::make('technician_id')
                            ->label('Transferir para qual Técnico?')
                            // CORREÇÃO: Usa o getTenant() para filtrar técnicos do cliente logado
                            ->options(fn () => User::where('tenant_id', Tenancy::current()->id)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Textarea::make('transfer_reason')
                            ->label('Motivo da Transferência')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $oldStatus = $this->record->status;
                        $oldTechnicianId = $this->record->technician_id;
                        $timeBefore = $this->record->total_time_seconds;

                        $this->record->update([
                            'technician_id' => $data['technician_id'],
                            'transfer_reason' => $data['transfer_reason'],
                            'status' => 'Aberto',
                        ]);

                        // O update() acima, se o status antigo era "Em Andamento", ja fechou
                        // o segmento do cronometro sozinho (MaintenanceOrder::updating()) --
                        // a diferenca antes/depois e' exatamente quanto o tecnico ANTIGO
                        // acumulou nesse segmento que acabou de fechar.
                        $segmentSeconds = $this->record->fresh()->total_time_seconds - $timeBefore;

                        $this->record->logStatusChange('Aberto', $oldStatus, 'Transferida: '.$data['transfer_reason'], null, [
                            'old_technician_id' => $oldTechnicianId,
                            'new_technician_id' => $data['technician_id'],
                            'segment_seconds' => $segmentSeconds,
                        ]);

                        Notification::make()
                            ->title('Técnico alterado')
                            ->success()
                            ->send();

                        if ($novoTecnico = User::find($data['technician_id'])) {
                            Notification::make()
                                ->title('Nova OS atribuída a você')
                                ->body('OS #'.$this->record->os_number.' foi transferida para você. Motivo: '.$data['transfer_reason'])
                                ->icon('heroicon-o-wrench-screwdriver')
                                ->iconColor('info')
                                ->actions([
                                    Action::make('ver')
                                        ->label('Ver OS')
                                        ->url(MaintenanceOrderResource::getUrl('edit', ['record' => $this->record]))
                                        ->button(),
                                ])
                                ->sendToDatabase($novoTecnico);
                        }

                        $this->refreshFormData(['technician_id', 'transfer_reason', 'status']);
                    }),

                Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->color('warning')
                    ->icon('heroicon-o-x-circle')
                    ->disabled(fn () => in_array($this->record->status, ['Concluída', 'Cancelado', 'Cancelada']))
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar Ordem de Serviço?')
                    ->form([
                        Textarea::make('cancel_reason')
                            ->label('Motivo do Cancelamento')
                            ->required()
                            ->placeholder('Informe o motivo...'),
                    ])
                    ->action(function (array $data) {
                        $oldStatus = $this->record->status;

                        $this->record->update([
                            'status' => 'Cancelado',
                            'cancel_reason' => $data['cancel_reason'],
                        ]);
                        $this->record->logStatusChange('Cancelado', $oldStatus, $data['cancel_reason']);

                        Notification::make()
                            ->title('OS Cancelada')
                            ->warning()
                            ->send();

                        $this->refreshFormData(['status', 'cancel_reason']);
                    }),

                Actions\DeleteAction::make(),
            ])
                ->label('Mais Ações')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }
}
