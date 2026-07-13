<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Appointment;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AgendaTecnicoWidget extends FullCalendarWidget
{
    public Model|string|null $model = Appointment::class;

    /**
     * Filtro de tecnico (admin apenas -- "" = todos). Nao-admin sempre ve so
     * os proprios compromissos/OS, igual ao restante do modulo.
     */
    public string $technicianId = '';

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'initialView' => 'timeGridWeek',
            'slotMinTime' => '07:00:00',
            'slotMaxTime' => '19:00:00',
        ];
    }

    /**
     * Mistura Appointment (compromissos livres), MaintenanceOrder
     * (scheduled_at) e EquipmentMovement (mobilizacao/desmobilizacao
     * agendada) no mesmo calendario. MaintenanceOrder/EquipmentMovement
     * sao so-leitura aqui -- ver onEventClick().
     *
     * Visibilidade: tecnico comum ve so os proprios eventos. Quem tem
     * alguma role com "Setor supervisionado" (roles.department_id, ver
     * RoleResource) ve tambem os eventos de todos os usuarios daquele(s)
     * setor(es) -- alem dos proprios, mesmo que nao pertenca ao setor.
     * Admin sempre ve tudo. EquipmentMovement nao tem technician_id (nao
     * e' "de" ninguem especifico -- e' operacao de patio, cruza setores
     * por natureza), entao fica visivel pra qualquer um que supervisione
     * algum setor, sem comparar setores.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return [];
        }

        $user = auth()->user();
        $start = Carbon::parse($fetchInfo['start']);
        $end = Carbon::parse($fetchInfo['end']);
        $supervisedDeptIds = $user->supervisedDepartmentIds();
        $canSeeSector = $user->isAdmin() || ! empty($supervisedDeptIds);

        $appointmentQuery = Appointment::where('tenant_id', $tenant->id)
            ->whereBetween('scheduled_at', [$start, $end]);

        $orderQuery = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('asset');

        if ($user->isAdmin()) {
            if ($this->technicianId !== '') {
                $appointmentQuery->where('technician_id', $this->technicianId);
                $orderQuery->where('technician_id', $this->technicianId);
            }
        } elseif (! empty($supervisedDeptIds)) {
            if ($this->technicianId !== '') {
                $appointmentQuery->where('technician_id', $this->technicianId);
                $orderQuery->where('technician_id', $this->technicianId);
            } else {
                $sectorUserIds = User::where('tenant_id', $tenant->id)
                    ->whereIn('department_id', $supervisedDeptIds)
                    ->pluck('id');

                $appointmentQuery->where(function ($q) use ($sectorUserIds, $user) {
                    $q->whereIn('technician_id', $sectorUserIds)->orWhere('technician_id', $user->id);
                });
                $orderQuery->where(function ($q) use ($sectorUserIds, $user) {
                    $q->whereIn('technician_id', $sectorUserIds)->orWhere('technician_id', $user->id);
                });
            }
        } else {
            $appointmentQuery->where('technician_id', $user->id);
            $orderQuery->where('technician_id', $user->id);
        }

        $appointmentEvents = $appointmentQuery->get()->map(function (Appointment $appointment) {
            $color = match (true) {
                $appointment->completed => '#059669',
                $appointment->urgente => '#dc2626',
                default => '#7c3aed',
            };

            return EventData::make()
                ->id($appointment->id)
                ->title($appointment->assunto)
                ->start($appointment->scheduled_at)
                ->backgroundColor($color)
                ->extendedProps(['type' => 'appointment']);
        });

        $orderEvents = $orderQuery->get()->map(function (MaintenanceOrder $order) {
            // Os valores reais de MaintenanceOrder.status sao em portugues,
            // Title Case ('Aberto', 'Em Andamento', 'Pendente', 'Concluída',
            // 'Cancelada' -- ver `MaintenanceOrder::whereNotNull('status')->distinct()`).
            // O match antigo so cobria variantes em ingles/minusculo, que
            // nunca batiam com nada real -- toda OS caia no cinza padrao,
            // quase invisivel no tema escuro do painel.
            $color = match ($order->status) {
                'Aberto', 'aberta', 'agendada', 'open' => '#2563eb',
                'Em Andamento', 'em_progresso', 'in_progress' => '#eab308',
                'Concluída', 'concluida', 'completed' => '#059669',
                'Pendente', 'pendente', 'pending' => '#f97316',
                'Cancelada', 'cancelada', 'cancelled' => '#dc2626',
                default => '#4b5563',
            };

            return EventData::make()
                ->id('order-'.$order->id)
                ->title('OS #'.($order->os_number ?? 'S/N').' — '.($order->asset?->name ?? 'Equipamento'))
                ->start($order->scheduled_at)
                ->backgroundColor($color)
                ->extendedProps([
                    'type' => 'order',
                    'url' => MaintenanceOrderResource::getUrl('edit', ['record' => $order]),
                ])
                // Arrastar tenta editar via $model do widget (Appointment) --
                // pra uma OS isso sempre daria 403 (resolveRecord nao acha
                // 'order-{id}' como Appointment). Reagendar OS ja tem tela
                // propria (a edicao da OS); sem arrastar aqui, sem 403 confuso.
                ->extraProperties(['editable' => false]);
        });

        $movementEvents = collect();

        if ($canSeeSector) {
            $movementEvents = EquipmentMovement::where('tenant_id', $tenant->id)
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [$start, $end])
                ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
                ->with(['asset', 'fleetDriver'])
                ->get()
                ->map(function (EquipmentMovement $movement) {
                    $isMobilizacao = $movement->type === EquipmentMovement::TYPE_MOBILIZACAO;
                    $label = $isMobilizacao ? 'Mobilização' : 'Desmobilização';

                    return EventData::make()
                        ->id('movement-'.$movement->id)
                        ->title($label.' — '.($movement->asset?->name ?? 'Equipamento'))
                        ->start($movement->scheduled_at)
                        ->backgroundColor($isMobilizacao ? '#9333ea' : '#ea580c')
                        ->extendedProps([
                            'type' => 'movement',
                            'driver' => $movement->fleetDriver?->name,
                        ])
                        // Mesmo motivo do 'order' acima -- sem tela de edicao
                        // de EquipmentMovement ainda, arrastar so daria 403.
                        ->extraProperties(['editable' => false]);
                });
        }

        // ->toArray() (nao ->all()) e' essencial aqui: EventData so implementa
        // Arrayable, com todas as propriedades protected e sem JsonSerializable
        // -- Collection::toArray() converte cada EventData num array puro
        // (chamando o toArray() dele), enquanto ->all() devolveria os objetos
        // EventData crus. Sem essa conversao, o Livewire serializa cada
        // evento como {} vazio pro JS (sem erro nenhum, silenciosamente) e o
        // FullCalendar nao desenha nada -- era esse o bug real por tras do
        // calendario aparecer "vazio" mesmo com os dados certos no banco.
        return $appointmentEvents->concat($orderEvents)->concat($movementEvents)->toArray();
    }

    /**
     * OS sao so-leitura no calendario (o CRUD delas vive no Kanban/Resource).
     * Clicar numa OS navega pra edicao dela em vez de tentar abrir a modal
     * de Appointment (o record nao existiria nessa model).
     */
    public function onEventClick(array $event): void
    {
        if (($event['extendedProps']['type'] ?? null) === 'order') {
            $this->redirect($event['extendedProps']['url']);

            return;
        }

        // Sem Resource dedicado pra EquipmentMovement ainda -- so mostra os
        // dados, sem tentar abrir a modal generica do FullCalendar (o
        // record nao existiria na model Appointment, que e' o $model
        // deste widget).
        if (($event['extendedProps']['type'] ?? null) === 'movement') {
            Notification::make()
                ->title($event['title'])
                ->body(
                    'Previsto para '.Carbon::parse($event['start'])->format('d/m/Y H:i')
                    .($event['extendedProps']['driver'] ? ' — Motorista: '.$event['extendedProps']['driver'] : '')
                )
                ->info()
                ->send();

            return;
        }

        parent::onEventClick($event);
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Agendamento')
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $form->fill([
                        'tipo_evento' => 'appointment',
                        'scheduled_at' => $arguments['start'] ?? now(),
                        'technician_id' => auth()->id(),
                    ]);
                })
                ->using(function (array $data) {
                    $tipo = $data['tipo_evento'] ?? 'appointment';

                    if (in_array($tipo, [EquipmentMovement::TYPE_MOBILIZACAO, EquipmentMovement::TYPE_DESMOBILIZACAO], true)) {
                        return EquipmentMovement::create([
                            'asset_id' => $data['asset_id'],
                            'maintenance_order_id' => $data['maintenance_order_id'] ?? null,
                            'type' => $tipo,
                            'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
                            'scheduled_at' => $data['scheduled_at'],
                        ]);
                    }

                    return Appointment::create([
                        'technician_id' => $data['technician_id'] ?? auth()->id(),
                        'assunto' => $data['assunto'],
                        'descricao' => $data['descricao'] ?? null,
                        'scheduled_at' => $data['scheduled_at'],
                        'urgente' => $data['urgente'] ?? false,
                    ]);
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function viewAction(): Action
    {
        return Actions\ViewAction::make();
    }

    public function getFormSchema(): array
    {
        $user = auth()->user();
        $canScheduleMovements = $user->isAdmin() || ! empty($user->supervisedDepartmentIds());
        $isAppointment = fn (Forms\Get $get) => ($get('tipo_evento') ?? 'appointment') === 'appointment';
        $isMovement = fn (Forms\Get $get) => in_array($get('tipo_evento'), [
            EquipmentMovement::TYPE_MOBILIZACAO,
            EquipmentMovement::TYPE_DESMOBILIZACAO,
        ], true);

        return [
            Forms\Components\Select::make('tipo_evento')
                ->label('Tipo de Evento')
                ->options([
                    'appointment' => 'Agendamento Pessoal',
                    EquipmentMovement::TYPE_MOBILIZACAO => 'Mobilização',
                    EquipmentMovement::TYPE_DESMOBILIZACAO => 'Desmobilização',
                ])
                ->default('appointment')
                ->required()
                ->reactive()
                ->visible($canScheduleMovements)
                ->columnSpanFull(),

            Forms\Components\Select::make('technician_id')
                ->label('Técnico')
                ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required($isAppointment)
                ->visible(fn (Forms\Get $get) => $user->isAdmin() && $isAppointment($get))
                ->default($user->id),
            Forms\Components\TextInput::make('assunto')
                ->label('Assunto')
                ->required($isAppointment)
                ->visible($isAppointment)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('descricao')
                ->label('Descrição')
                ->visible($isAppointment)
                ->columnSpanFull(),
            Forms\Components\Toggle::make('urgente')
                ->label('Urgente')
                ->visible($isAppointment),
            Forms\Components\Toggle::make('completed')
                ->label('Concluído')
                ->visible(fn (string $operation, Forms\Get $get) => $operation === 'edit' && $isAppointment($get)),

            Forms\Components\Select::make('asset_id')
                ->label('Equipamento')
                ->options(fn () => Asset::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required($isMovement)
                ->visible($isMovement)
                ->reactive()
                ->columnSpanFull(),
            Forms\Components\Select::make('maintenance_order_id')
                ->label('Ordem de Serviço (opcional)')
                ->helperText('Se selecionada, o app do operador vai reaproveitar este mesmo agendamento ao começar a mobilização/desmobilização no pátio.')
                ->options(function (Forms\Get $get) {
                    if (! $get('asset_id')) {
                        return [];
                    }

                    return MaintenanceOrder::where('tenant_id', Tenancy::current()?->id)
                        ->where('asset_id', $get('asset_id'))
                        ->whereNotIn('status', ['Concluída', 'Cancelada'])
                        ->get()
                        ->mapWithKeys(fn (MaintenanceOrder $order) => [
                            $order->id => 'OS #'.($order->os_number ?? 'S/N').' — '.($order->description ?? ''),
                        ]);
                })
                ->searchable()
                ->visible($isMovement)
                ->columnSpanFull(),

            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data/Hora')
                ->required(),
        ];
    }
}
