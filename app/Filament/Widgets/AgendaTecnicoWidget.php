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
     * (scheduled_at) no mesmo calendario, igual a antiga grade semanal.
     * MaintenanceOrder e' so-leitura aqui -- ver onEventClick().
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

        $appointmentQuery = Appointment::where('tenant_id', $tenant->id)
            ->whereBetween('scheduled_at', [$start, $end]);

        $orderQuery = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('asset');

        if (! $user->isAdmin()) {
            $appointmentQuery->where('technician_id', $user->id);
            $orderQuery->where('technician_id', $user->id);
        } elseif ($this->technicianId !== '') {
            $appointmentQuery->where('technician_id', $this->technicianId);
            $orderQuery->where('technician_id', $this->technicianId);
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
            $color = match ($order->status) {
                'agendada', 'open' => '#2563eb',
                'em_progresso', 'in_progress' => '#eab308',
                'concluida', 'completed', 'Concluída' => '#059669',
                'pendente', 'pending' => '#f97316',
                'cancelada', 'cancelled', 'Cancelada' => '#dc2626',
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
                ]);
        });

        return $appointmentEvents->concat($orderEvents)->all();
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
