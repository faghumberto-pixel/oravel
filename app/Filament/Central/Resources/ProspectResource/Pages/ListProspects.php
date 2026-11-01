<?php

namespace App\Filament\Central\Resources\ProspectResource\Pages;

use App\Filament\Central\Resources\ProspectResource;
use App\Models\Prospect;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProspects extends ListRecords
{
    protected static string $resource = ProspectResource::class;

    protected static string $view = 'filament.central.prospects-list';

    /** Cards clicaveis acima da tabela: cada um aplica um conjunto de filtros (chave => valor normalizado). */
    public const CARDS = [
        'total' => ['Total', []],
        'nao_enviado' => ['Não enviei', ['status' => 'nao_enviado']],
        'enviado' => ['Enviei', ['status' => 'enviado']],
        'respondeu' => ['Respondeu', ['status' => 'respondeu']],
        'em_teste' => ['Em teste', ['status' => 'em_teste']],
        'sem_interesse' => ['Sem interesse', ['status' => 'sem_interesse']],
        'nova' => ['Empresas novas', ['empresa_nova' => '1']],
        'campinas' => ['Região de Campinas', ['regiao_campinas' => '1']],
        'nota_alta' => ['Nota 8 ou mais', ['nota_alta' => '1']],
        'retorno' => ['Retorno vencido', ['retorno_vencido' => '1']],
    ];

    public function filtrarCard(string $chave): void
    {
        $filtros = self::CARDS[$chave][1] ?? [];

        $this->resetTableFiltersForm();
        $estado = [];
        foreach ($filtros as $k => $valor) {
            $estado[$k] = in_array($k, ['nota_alta', 'retorno_vencido'], true) ? ['isActive' => true] : ['value' => $valor];
        }
        $this->tableFilters = array_replace($this->tableFilters ?? [], $estado);
        $this->resetPage();
    }

    public function cards(): array
    {
        $ativos = Prospect::filtrosAtivos($this->tableFilters);
        $cards = [];
        foreach (self::CARDS as $chave => [$rotulo, $filtros]) {
            $estado = [];
            foreach ($filtros as $k => $valor) {
                $estado[$k] = ['value' => $valor, 'isActive' => true];
            }
            $cards[] = [
                'chave' => $chave,
                'rotulo' => $rotulo,
                'total' => Prospect::filtrar($estado)->count(),
                'ativo' => $ativos == $filtros,
            ];
        }

        return $cards;
    }

    /** Abre o PDF (gerado em PHP) com os filtros, a busca e a ordem que estão na tela. */
    public function urlImpressao(): string
    {
        return route('central.prospects.imprimir', array_filter([
            'filtros' => $this->tableFilters,
            'busca' => $this->tableSearch,
        ]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('imprimir')->label('Imprimir')->icon('heroicon-o-printer')->color('gray')
                ->url(fn (): string => $this->urlImpressao())->openUrlInNewTab(),
            Actions\CreateAction::make()->label('Novo prospect'),
        ];
    }
}
