<?php

namespace App\Filament\Concerns;

use App\Support\CrmPalette;

/**
 * Cards de totais clicaveis acima de uma lista (padrao do app, igual aos Prospects da Central).
 *
 * A pagina define `definicaoCartoes()`: chave => [rotulo, [filtro => valor], cor da paleta CrmPalette].
 * Cada card e' um recorte sobre os filtros de SelectFilter da tabela; o total e' calculado com a
 * propria query da tabela (busca e demais filtros ja aplicados), trocando so' os filtros do card.
 */
trait HasCartoesFiltro
{
    /** @return array<string, array{0: string, 1: array<string, string>, 2: string}> */
    abstract protected function definicaoCartoes(): array;

    /** Filtros controlados pelos cards (os demais valem como "base" para todos). */
    protected function chavesCartao(): array
    {
        $chaves = [];
        foreach ($this->definicaoCartoes() as [, $filtros]) {
            $chaves = array_merge($chaves, array_keys($filtros));
        }

        return array_values(array_unique($chaves));
    }

    private function estadoComCartao(array $atual, array $filtros): array
    {
        foreach ($this->chavesCartao() as $k) {
            $atual[$k] = ['value' => null];
        }
        foreach ($filtros as $k => $valor) {
            $atual[$k] = ['value' => $valor];
        }

        return $atual;
    }

    public function filtrarCard(string $chave): void
    {
        $this->tableFilters = $this->estadoComCartao($this->tableFilters ?? [], $this->definicaoCartoes()[$chave][1] ?? []);
        // Atribuir pelo servidor nao dispara o gancho do Filament que grava na sessao.
        $this->updatedTableFilters();
        $this->resetPage();
    }

    public function cards(): array
    {
        $original = $this->tableFilters ?? [];
        $ativos = [];
        foreach ($this->chavesCartao() as $k) {
            if (filled($original[$k]['value'] ?? null)) {
                $ativos[$k] = (string) $original[$k]['value'];
            }
        }

        $cards = [];
        try {
            foreach ($this->definicaoCartoes() as $chave => [$rotulo, $filtros, $cor]) {
                $this->tableFilters = $this->estadoComCartao($original, $filtros);
                $cards[] = [
                    'chave' => $chave,
                    'rotulo' => $rotulo,
                    'total' => $this->getFilteredTableQuery()->reorder()->count(),
                    'ativo' => $ativos == array_map('strval', $filtros),
                    'paleta' => CrmPalette::cor($cor),
                ];
            }
        } finally {
            $this->tableFilters = $original;
        }

        return $cards;
    }
}
