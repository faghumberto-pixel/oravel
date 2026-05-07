<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class EditMaintenanceOrder extends EditRecord
{
    protected static string $resource = MaintenanceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. BOTÃO INICIAR / CONTINUAR
            Actions\Action::make('iniciar')
                ->label(fn () => $this->record->started_at ? 'Continuar Serviço' : 'Iniciar Serviço')
                ->color('success')
                ->icon('heroicon-o-play')
                // Fica visível sempre, mas desabilita se NÃO estiver nestes status:
                ->disabled(fn () => !in_array($this->record->status, ['Aberto', 'Pendente', 'Pausada', 'Reprogramado', 'Reprogramada']))
                ->action(function () {
                    $data = [
                        'status' => 'Em Andamento',
                        'last_timer_start' => now(),
                    ];

                    if (!$this->record->started_at) {
                        $data['started_at'] = now();
                    }

                    $this->record->update($data);
                    
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
                // Desabilita se NÃO estiver em andamento
                ->disabled(fn () => $this->record->status !== 'Em Andamento')
                ->action(function () {
                    $secondsSinceStart = $this->record->last_timer_start 
                        ? now()->diffInSeconds($this->record->last_timer_start) 
                        : 0;

                    $this->record->update([
                        'status' => 'Pausada',
                        'total_time_seconds' => (int) ($this->record->total_time_seconds + $secondsSinceStart),
                        'last_timer_start' => null,
                    ]);

                    Notification::make()
                        ->title('Serviço pausado')
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            // 3. BOTÃO REPROGRAMAR
            Actions\Action::make('reprogramar')
                ->label('Reprogramar')
                ->color('info')
                ->icon('heroicon-o-calendar')
                // Permite clicar mesmo rodando
                ->disabled(fn () => !in_array($this->record->status, ['Aberto', 'Pendente', 'Pausada', 'Em Andamento']))
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
                    $secondsSinceStart = ($this->record->status === 'Em Andamento' && $this->record->last_timer_start) 
                        ? now()->diffInSeconds($this->record->last_timer_start) 
                        : 0;

                    $this->record->update([
                        'status' => 'Reprogramado', 
                        'rescheduled_to' => $data['rescheduled_to'],
                        'reschedule_reason' => $data['reschedule_reason'],
                        'total_time_seconds' => (int) ($this->record->total_time_seconds + $secondsSinceStart),
                        'last_timer_start' => null,
                    ]);

                    Notification::make()
                        ->title('OS Reprogramada')
                        ->info()
                        ->send();

                    $this->refreshFormData(['status', 'rescheduled_to', 'reschedule_reason']);
                }),

            // 4. BOTÃO TRANSFERIR
            Actions\Action::make('transferir')
                ->label('Transferir')
                ->color('gray')
                ->icon('heroicon-o-arrows-right-left')
                ->disabled(fn () => !in_array($this->record->status, ['Aberto', 'Pendente', 'Pausada', 'Reprogramado', 'Reprogramada', 'Em Andamento']))
                ->form([
                    Select::make('technician_id')
                        ->label('Transferir para qual Técnico?')
                        ->options(User::where('tenant_id', Auth::user()->tenant_id)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Textarea::make('transfer_reason')
                        ->label('Motivo da Transferência')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $secondsSinceStart = ($this->record->status === 'Em Andamento' && $this->record->last_timer_start) 
                        ? now()->diffInSeconds($this->record->last_timer_start) 
                        : 0;

                    $this->record->update([
                        'technician_id' => $data['technician_id'],
                        'transfer_reason' => $data['transfer_reason'],
                        'status' => 'Aberto', 
                        'total_time_seconds' => (int) ($this->record->total_time_seconds + $secondsSinceStart),
                        'last_timer_start' => null,
                    ]);

                    Notification::make()
                        ->title('Técnico alterado')
                        ->success()
                        ->send();

                    $this->refreshFormData(['technician_id', 'transfer_reason', 'status']);
                }),

            // 5. BOTÃO CANCELAR
            Actions\Action::make('cancelar')
                ->label('Cancelar')
                ->color('warning')
                ->icon('heroicon-o-x-circle')
                // Desabilita apenas se já estiver concluída ou cancelada
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
                    $secondsSinceStart = ($this->record->status === 'Em Andamento' && $this->record->last_timer_start) 
                        ? now()->diffInSeconds($this->record->last_timer_start) 
                        : 0;

                    $this->record->update([
                        'status' => 'Cancelado',
                        'cancel_reason' => $data['cancel_reason'],
                        'total_time_seconds' => (int) ($this->record->total_time_seconds + $secondsSinceStart),
                        'last_timer_start' => null,
                    ]);

                    Notification::make()
                        ->title('OS Cancelada')
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status', 'cancel_reason']);
                }),

            // 6. BOTÃO CONCLUIR
            Actions\Action::make('concluir')
                ->label('Concluir')
                ->color('danger') 
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Concluir Ordem de Serviço?')
                ->modalDescription('O tempo será totalizado e a OS será finalizada para faturamento/fechamento.')
                // Desabilita se NÃO estiver em andamento ou pausada
                ->disabled(fn () => !in_array($this->record->status, ['Em Andamento', 'Pausada']))
                ->action(function () {
                    $secondsSinceStart = $this->record->last_timer_start 
                        ? now()->diffInSeconds($this->record->last_timer_start) 
                        : 0;

                    $this->record->update([
                        'status' => 'Concluída',
                        'finished_at' => now(),
                        'total_time_seconds' => (int) ($this->record->total_time_seconds + $secondsSinceStart),
                        'last_timer_start' => null,
                    ]);

                    Notification::make()
                        ->title('Ordem de Serviço Concluída!')
                        ->success()
                        ->persistent()
                        ->send();

                    $this->refreshFormData(['status', 'finished_at']);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}