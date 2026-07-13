<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

/**
 * Programacao de Logistica: so Mobilizacao/Desmobilizacao agendada
 * (EquipmentMovement.scheduled_at). Nao mistura com Agendamento
 * pessoal/O.S. da Manutencao (ver AgendaTecnicoWidget) -- cada
 * departamento tem sua propria Programacao.
 *
 * Visibilidade: EquipmentMovement nao tem dono/tecnico especifico (e'
 * operacao de patio, cruza setores por natureza) -- visivel pra admin ou
 * qualquer usuario com alguma role de "Setor supervisionado"
 * (roles.department_id, ver RoleResource), sem comparar setor especifico.
 * Tecnico comum sem esse privilegio nao ve esta pagina (ver
 * ProgramacaoLogistica::canAccess()).
 */
class LogisticaAgendaWidget extends FullCalendarWidget
{
    public Model|string|null $model = EquipmentMovement::class;

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

    public function fetchEvents(array $fetchInfo): array
    {
        $tenant = Tenancy::current();
        $user = auth()->user();
        if (! $tenant || ! ($user->isAdmin() || ! empty($user->supervisedDepartmentIds()))) {
            return [];
        }

        $start = Carbon::parse($fetchInfo['start']);
        $end = Carbon::parse($fetchInfo['end']);

        $movements = EquipmentMovement::where('tenant_id', $tenant->id)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
            ->with(['asset', 'fleetDriver'])
            ->get()
            ->map(function (EquipmentMovement $movement) {
                $isMobilizacao = $movement->type === EquipmentMovement::TYPE_MOBILIZACAO;
                $label = $isMobilizacao ? 'Mobilização' : 'Desmobilização';

                return EventData::make()
                    ->id($movement->id)
                    ->title($label.' — '.($movement->asset?->name ?? 'Equipamento'))
                    ->start($movement->scheduled_at)
                    ->backgroundColor($isMobilizacao ? '#9333ea' : '#ea580c')
                    ->extendedProps([
                        'type' => $movement->type,
                        'driver' => $movement->fleetDriver?->name,
                    ]);
            });

        // ->toArray() (nao ->all()) e' essencial -- ver AgendaTecnicoWidget
        // pro motivo (EventData so implementa Arrayable, sem isso o Livewire
        // manda {} vazio pro JS e o calendario fica sempre vazio).
        return $movements->toArray();
    }

    /**
     * Ainda sem tela de edicao dedicada de EquipmentMovement (so a listagem
     * so-leitura em EquipmentMovementResource) -- clicar so mostra os dados,
     * sem tentar abrir modal generica (o record nao existiria de outra forma
     * que nao seja o proprio EquipmentMovement, mas ainda nao ha form de
     * edicao pronto pra ele aqui).
     */
    public function onEventClick(array $event): void
    {
        Notification::make()
            ->title($event['title'])
            ->body(
                'Previsto para '.Carbon::parse($event['start'])->format('d/m/Y H:i')
                .($event['extendedProps']['driver'] ? ' — Motorista: '.$event['extendedProps']['driver'] : '')
            )
            ->info()
            ->send();
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Agendar Mobilização/Desmobilização')
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $form->fill([
                        'type' => EquipmentMovement::TYPE_MOBILIZACAO,
                        'scheduled_at' => $arguments['start'] ?? now(),
                    ]);
                })
                ->using(fn (array $data) => EquipmentMovement::create([
                    'asset_id' => $data['asset_id'],
                    'maintenance_order_id' => $data['maintenance_order_id'] ?? null,
                    'type' => $data['type'],
                    'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
                    'scheduled_at' => $data['scheduled_at'],
                ])),
        ];
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    EquipmentMovement::TYPE_MOBILIZACAO => 'Mobilização',
                    EquipmentMovement::TYPE_DESMOBILIZACAO => 'Desmobilização',
                ])
                ->required(),
            Forms\Components\Select::make('asset_id')
                ->label('Equipamento')
                ->options(fn () => Asset::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required()
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
                ->columnSpanFull(),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data/Hora')
                ->required(),
        ];
    }
}
