<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Appointment;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

/**
 * Programacao da Manutencao: Agendamento pessoal (Appointment) + O.S. com
 * scheduled_at. Mobilizacao/Desmobilizacao NAO entram aqui de proposito --
 * cada departamento tem sua propria Programacao, sem misturar (ver
 * LogisticaAgendaWidget para o equivalente de Logistica). Nao confundir o
 * calendario de "o que a Manutencao tem que fazer" com "o que vai sair do
 * patio".
 */
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
     * Mistura Appointment (compromissos livres) e MaintenanceOrder
     * (scheduled_at) no mesmo calendario. MaintenanceOrder e' so-leitura
     * aqui -- ver onEventClick().
     *
     * Visibilidade: tecnico comum ve so os proprios eventos. Quem tem
     * alguma role com "Setor supervisionado" (roles.department_id, ver
     * RoleResource) ve tambem os eventos de todos os usuarios daquele(s)
     * setor(es) -- alem dos proprios, mesmo que nao pertenca ao setor.
     * Admin sempre ve tudo.
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

        // ->toArray() (nao ->all()) e' essencial aqui: EventData so implementa
        // Arrayable, com todas as propriedades protected e sem JsonSerializable
        // -- Collection::toArray() converte cada EventData num array puro
        // (chamando o toArray() dele), enquanto ->all() devolveria os objetos
        // EventData crus. Sem essa conversao, o Livewire serializa cada
        // evento como {} vazio pro JS (sem erro nenhum, silenciosamente) e o
        // FullCalendar nao desenha nada.
        return $appointmentEvents->concat($orderEvents)->toArray();
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

        parent::onEventClick($event);
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Agendamento')
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $form->fill([
                        'scheduled_at' => $arguments['start'] ?? now(),
                        'technician_id' => auth()->id(),
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

        return [
            Forms\Components\Select::make('technician_id')
                ->label('Técnico')
                ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required()
                ->visible($user->isAdmin())
                ->default($user->id),
            Forms\Components\TextInput::make('assunto')
                ->label('Assunto')
                ->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('descricao')
                ->label('Descrição')
                ->columnSpanFull(),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data/Hora')
                ->required(),
            Forms\Components\Toggle::make('urgente')
                ->label('Urgente'),
            Forms\Components\Toggle::make('completed')
                ->label('Concluído')
                ->visible(fn (string $operation) => $operation === 'edit'),
        ];
    }
}
