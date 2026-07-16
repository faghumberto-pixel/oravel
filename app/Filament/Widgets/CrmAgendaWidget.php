<?php

namespace App\Filament\Widgets;

use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CrmAgendaWidget extends FullCalendarWidget
{
    public Model|string|null $model = CrmLeadInteraction::class;

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

    /**
     * O calendario mostra o proximo follow-up de cada interação registrada
     * (nao a data em que o contato aconteceu) -- e o que precisa de ação.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $user = auth()->user();

        $query = CrmLeadInteraction::query()
            ->whereNotNull('next_followup_date')
            ->whereBetween('next_followup_date', [
                Carbon::parse($fetchInfo['start'])->toDateString(),
                Carbon::parse($fetchInfo['end'])->toDateString(),
            ])
            ->with('lead');

        if (! $user->canSeeAllCrmLeads()) {
            $query->whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id));
        }

        return $query->get()
            ->filter(fn (CrmLeadInteraction $interaction) => $interaction->lead !== null)
            ->map(function (CrmLeadInteraction $interaction) {
                $isPast = $interaction->next_followup_date->isPast();
                $isToday = $interaction->next_followup_date->isToday();

                return EventData::make()
                    ->id($interaction->id)
                    ->title($interaction->lead->name.($interaction->lead->company_name ? ' — '.$interaction->lead->company_name : ''))
                    ->start($interaction->next_followup_date->toDateString())
                    ->allDay(true)
                    ->backgroundColor(match (true) {
                        $isPast => '#dc2626',
                        $isToday => '#f59e0b',
                        default => '#E8541A',
                    });
            })
            // ->toArray() (nao ->all()) e' essencial -- EventData so
            // implementa Arrayable, com propriedades protected e sem
            // JsonSerializable. Sem a conversao, o Livewire manda {} vazio
            // pro JS pra cada evento (sem erro), e o FullCalendar nao
            // desenha nada. Mesmo bug encontrado e corrigido em
            // AgendaTecnicoWidget::fetchEvents().
            ->toArray();
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Registrar Contato')
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $form->fill([
                        'contact_date' => now(),
                        'next_followup_date' => $arguments['start'] ?? null,
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $lead = CrmLead::find($data['crm_lead_id']);
                    $data['user_id'] = auth()->id();
                    $data['stage_at_time'] = $lead?->stage;

                    return $data;
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->mountUsing(function (CrmLeadInteraction $record, Forms\Form $form, array $arguments) {
                    $form->fill([
                        ...$record->toArray(),
                        'next_followup_date' => $arguments['event']['start'] ?? $record->next_followup_date,
                    ]);
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function viewAction(): Action
    {
        return Actions\ViewAction::make();
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('crm_lead_id')
                ->label('Lead')
                ->options(fn () => CrmLead::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required()
                ->disabledOn('edit'),
            Forms\Components\DateTimePicker::make('contact_date')
                ->label('Data do Contato')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('channel')
                ->label('Canal')
                ->options(CrmLeadInteraction::channelLabels())
                ->required(),
            Forms\Components\Textarea::make('summary')
                ->label('Resumo do Contato')
                ->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('next_action')
                ->label('Próxima Ação')
                ->columnSpanFull(),
            Forms\Components\DatePicker::make('next_followup_date')
                ->label('Próximo Follow-up'),
        ];
    }
}
