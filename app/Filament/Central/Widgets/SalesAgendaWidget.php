<?php

namespace App\Filament\Central\Widgets;

use App\Models\SalesLead;
use App\Models\SalesLeadAppointment;
use Carbon\Carbon;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

/**
 * Agenda de compromissos do CRM comercial ("Programacao") -- mesmo padrao
 * de CrmAgendaWidget/AgendaTecnicoWidget, cor por status do compromisso
 * (pendente/aguardando/em_andamento/concluido), nao por passado/hoje/futuro.
 */
class SalesAgendaWidget extends FullCalendarWidget
{
    public Model|string|null $model = SalesLeadAppointment::class;

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'initialView' => 'dayGridMonth',
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return SalesLeadAppointment::query()
            ->whereBetween('scheduled_at', [
                Carbon::parse($fetchInfo['start']),
                Carbon::parse($fetchInfo['end']),
            ])
            ->with('lead')
            ->get()
            ->map(fn (SalesLeadAppointment $appointment) => EventData::make()
                ->id($appointment->id)
                ->title($appointment->title.($appointment->lead?->company_name ? ' — '.$appointment->lead->company_name : ''))
                ->start($appointment->scheduled_at->toIso8601String())
                ->backgroundColor(SalesLeadAppointment::statusColors()[$appointment->status] ?? '#6b7280'))
            // ->toArray() de proposito, nao ->all() -- mesmo motivo
            // documentado em CrmAgendaWidget/AgendaTecnicoWidget:
            // EventData so implementa Arrayable, sem isso o FullCalendar
            // recebe {} vazio e nao desenha nada.
            ->toArray();
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Agendar Compromisso')
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $form->fill([
                        'scheduled_at' => $arguments['start'] ?? now(),
                        'status' => SalesLeadAppointment::STATUS_PENDENTE,
                    ]);
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->mountUsing(function (SalesLeadAppointment $record, Forms\Form $form, array $arguments) {
                    $form->fill([
                        ...$record->toArray(),
                        'scheduled_at' => $arguments['event']['start'] ?? $record->scheduled_at,
                    ]);
                }),
            Actions\DeleteAction::make(),
        ];
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('sales_lead_id')
                ->label('Lead')
                ->options(fn () => SalesLead::pluck('company_name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('title')
                ->label('Título')
                ->required(),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options(SalesLeadAppointment::typeLabels())
                ->default(SalesLeadAppointment::TYPE_DEMONSTRACAO)
                ->required(),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Data/Hora')
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(SalesLeadAppointment::statusLabels())
                ->default(SalesLeadAppointment::STATUS_PENDENTE)
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ];
    }
}
