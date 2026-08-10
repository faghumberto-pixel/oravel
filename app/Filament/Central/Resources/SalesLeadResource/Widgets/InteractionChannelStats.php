<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Widgets;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "Quantidade de clientes que enviei email, quais foram, os que liguei,
 * os que mandei msg via whatsapp, os que fiz reuniao presencial ou online,
 * os que fiz visita" (pedido do usuario 2026-08-10) -- 1 card por canal,
 * contando LEADS DISTINTOS (nao interacoes -- "quantos clientes", nao
 * "quantas ligacoes"), clicavel pra ver quais via
 * SalesLeadResource::getUrl('index', ['tableFilters[interaction_channel]...']).
 */
class InteractionChannelStats extends BaseWidget
{
    protected function getStats(): array
    {
        $channels = [
            SalesLeadInteraction::CHANNEL_EMAIL => ['label' => 'Leads Contatados por E-mail', 'icon' => 'heroicon-m-envelope', 'color' => 'info'],
            SalesLeadInteraction::CHANNEL_TELEFONE => ['label' => 'Leads Contatados por Telefone', 'icon' => 'heroicon-m-phone', 'color' => 'success'],
            SalesLeadInteraction::CHANNEL_WHATSAPP => ['label' => 'Leads Contatados por WhatsApp', 'icon' => 'heroicon-m-chat-bubble-left-right', 'color' => 'success'],
            SalesLeadInteraction::CHANNEL_REUNIAO_PRESENCIAL => ['label' => 'Reuniões Presenciais Feitas', 'icon' => 'heroicon-m-user-group', 'color' => 'warning'],
            SalesLeadInteraction::CHANNEL_REUNIAO_ONLINE => ['label' => 'Reuniões Online Feitas', 'icon' => 'heroicon-m-video-camera', 'color' => 'warning'],
            SalesLeadInteraction::CHANNEL_VISITA => ['label' => 'Visitas Feitas', 'icon' => 'heroicon-m-map-pin', 'color' => 'primary'],
        ];

        return collect($channels)->map(function (array $meta, string $channel) {
            $count = SalesLead::whereHas('interactions', fn ($q) => $q->where('channel', $channel))->count();

            return Stat::make($meta['label'], $count)
                ->description($count > 0 ? 'Clique para ver quais' : 'Nenhum contato registrado ainda')
                ->descriptionIcon($meta['icon'])
                ->color($count > 0 ? $meta['color'] : 'gray')
                ->url(SalesLeadResource::getUrl('index', ['tableFilters[interaction_channel][value]' => $channel]));
        })->values()->all();
    }
}
