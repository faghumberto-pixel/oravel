<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Linha do Tempo da Locação: agrega os 4 eventos de
 * SolicitacaoLocacao::timelineEvents() (Comercial, Manutenção, Logística,
 * Portaria) numa única tela por Solicitação -- a lacuna documentada no
 * "Estudo de Caso" (nenhuma tela hoje mostra o pedido do início ao fim).
 */
class TimelineSolicitacaoLocacao extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SolicitacaoLocacaoResource::class;

    protected static string $view = 'filament.resources.solicitacao-locacao-resource.pages.timeline-solicitacao-locacao';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return 'Linha do Tempo da Locação';
    }

    public function getEvents(): Collection
    {
        return $this->getRecord()->timelineEvents();
    }

    /**
     * Eventos agrupados por dia (mesma UX do estudo de caso) -- chave no
     * formato d/m/Y pra ordenar naturalmente junto com sortBy('at').
     */
    public function getEventsByDay(): Collection
    {
        return $this->getEvents()->groupBy(fn (array $event) => $event['at']->format('d/m/Y'));
    }
}
