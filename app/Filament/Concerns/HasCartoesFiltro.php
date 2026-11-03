<?php

namespace App\Filament\Concerns;

use App\Support\CrmPalette;

/**
 * Cards de totais clicaveis acima de uma lista (padrao do app, igual aos Prospects da Central).
 *
 * A pagina define `definicaoCartoes()`: chave => [rotulo, [filtro => valor], cor da paleta CrmPalette].
 * Valor string = SelectFilter (['value' => x]); valor `true` = filtro liga/desliga (['isActive' => true]);
 * valor array = SelectFilter multiplo (['values' => [...]]).
 * Cada card e' um recorte sobre os filtros da tabela; o total e' calculado com a
 * propria query da tabela (busca e demais filtros ja aplicados), trocando so' os filtros do card.
 */
trait HasCartoesFiltro
{
    /** @return array<string, array{0: string, 1: array<string, string|bool|array<int, string>>, 2: string}> */
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

    /** Chaves dos filtros liga/desliga controlados pelos cards. */
    private function chavesToggle(): array
    {
        $chaves = [];
        foreach ($this->definicaoCartoes() as [, $filtros]) {
            foreach ($filtros as $k => $valor) {
                if ($valor === true) {
                    $chaves[] = $k;
                }
            }
        }

        return array_values(array_unique($chaves));
    }

    /** Chaves dos filtros de selecao multipla controlados pelos cards. */
    private function chavesMultiplas(): array
    {
        $chaves = [];
        foreach ($this->definicaoCartoes() as [, $filtros]) {
            foreach ($filtros as $k => $valor) {
                if (is_array($valor)) {
                    $chaves[] = $k;
                }
            }
        }

        return array_values(array_unique($chaves));
    }

    private function estadoComCartao(array $atual, array $filtros): array
    {
        $toggles = $this->chavesToggle();
        $multiplas = $this->chavesMultiplas();
        foreach ($this->chavesCartao() as $k) {
            $atual[$k] = in_array($k, $toggles, true) ? ['isActive' => false] : (in_array($k, $multiplas, true) ? ['values' => []] : ['value' => null]);
        }
        foreach ($filtros as $k => $valor) {
            $atual[$k] = $valor === true ? ['isActive' => true] : (is_array($valor) ? ['values' => $valor] : ['value' => $valor]);
        }

        return $atual;
    }

    private function normalizarValor(mixed $valor): string
    {
        return $valor === true ? '1' : (is_array($valor) ? implode(',', $valor) : (string) $valor);
    }

    /** Estado atual dos filtros dos cards, normalizado (chave => texto) para comparar com a definicao. */
    private function ativosNormalizados(array $estado): array
    {
        $toggles = $this->chavesToggle();
        $multiplas = $this->chavesMultiplas();
        $r = [];
        foreach ($this->chavesCartao() as $k) {
            if (in_array($k, $toggles, true)) {
                if (! empty($estado[$k]['isActive'])) {
                    $r[$k] = '1';
                }
            } elseif (in_array($k, $multiplas, true)) {
                if (! empty($estado[$k]['values'])) {
                    $r[$k] = implode(',', $estado[$k]['values']);
                }
            } elseif (filled($estado[$k]['value'] ?? null)) {
                $r[$k] = (string) $estado[$k]['value'];
            }
        }

        return $r;
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
        $ativos = $this->ativosNormalizados($original);

        $cards = [];
        try {
            foreach ($this->definicaoCartoes() as $chave => [$rotulo, $filtros, $cor]) {
                $this->tableFilters = $this->estadoComCartao($original, $filtros);
                $cards[] = [
                    'chave' => $chave,
                    'rotulo' => $rotulo,
                    'total' => $this->getFilteredTableQuery()->reorder()->count(),
                    'ativo' => $ativos == array_map(fn ($v) => $this->normalizarValor($v), $filtros),
                    'paleta' => CrmPalette::cor($cor),
                ];
            }
        } finally {
            $this->tableFilters = $original;
        }

        return $cards;
    }
}
