<?php

namespace App\Filament\Central\Resources\SalesLeadResource\RelationManagers;

use App\Models\SalesLeadAppointment;
use App\Models\SalesLeadInteraction;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Collection;

/**
 * Aba "Histórico" -- une interactions (Follow Up) e appointments
 * (Compromissos) numa única linha do tempo, mais recente primeiro.
 * Read-only por natureza: edição continua nas abas originais (Follow Up /
 * Compromissos), essa aba existe só pra dar visão consolidada de "o que já
 * fiz, o que falei, o que ficou de fazer" sem trocar de aba. Usa
 * `interactions` como $relationship só para o RelationManager funcionar
 * como aba do recurso -- os dados exibidos vêm de getTimelineItems(), que
 * combina os dois relacionamentos em PHP.
 */
class TimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Histórico';

    protected static ?string $icon = 'heroicon-o-clock';

    protected static string $view = 'filament.central.resources.sales-lead-resource.relation-managers.timeline-relation-manager';

    public function getTimelineItems(): Collection
    {
        $lead = $this->getOwnerRecord();

        $interactions = $lead->interactions()->with('user:id,name')->get()->map(fn (SalesLeadInteraction $i) => [
            'kind' => 'interaction',
            'at' => $i->contact_date,
            'title' => SalesLeadInteraction::channelLabels()[$i->channel] ?? $i->channel,
            'body' => $i->summary,
            'author' => $i->user?->name,
            'meta' => $i->stage_at_time,
            'color' => 'gray',
            'icon' => match ($i->channel) {
                SalesLeadInteraction::CHANNEL_TELEFONE => 'heroicon-o-phone',
                SalesLeadInteraction::CHANNEL_EMAIL => 'heroicon-o-envelope',
                SalesLeadInteraction::CHANNEL_WHATSAPP => 'heroicon-o-chat-bubble-left-right',
                SalesLeadInteraction::CHANNEL_REUNIAO_PRESENCIAL, SalesLeadInteraction::CHANNEL_REUNIAO_ONLINE => 'heroicon-o-users',
                SalesLeadInteraction::CHANNEL_VISITA => 'heroicon-o-map-pin',
                default => 'heroicon-o-pencil-square',
            },
        ]);

        $appointments = $lead->appointments()->with('assignedUser:id,name')->get()->map(fn (SalesLeadAppointment $a) => [
            'kind' => 'appointment',
            'at' => $a->scheduled_at,
            'title' => $a->title,
            'body' => $a->notes,
            'author' => $a->assignedUser?->name,
            'meta' => SalesLeadAppointment::statusLabels()[$a->status] ?? $a->status,
            'color' => match ($a->status) {
                SalesLeadAppointment::STATUS_CONCLUIDO => 'success',
                SalesLeadAppointment::STATUS_EM_ANDAMENTO => 'info',
                SalesLeadAppointment::STATUS_AGUARDANDO => 'warning',
                default => 'danger',
            },
            'icon' => 'heroicon-o-calendar-days',
        ]);

        return $interactions->concat($appointments)
            ->sortByDesc(fn (array $item) => $item['at'])
            ->values();
    }
}
