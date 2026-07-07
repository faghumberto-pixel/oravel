<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Botao "Imprimir" para paginas de listagem Filament: imprime exatamente os
 * registros que estao visiveis com os filtros/busca/ordenacao atuais da
 * tabela (getFilteredSortedTableQuery), numa view HTML minimalista pronta
 * pra Ctrl+P -- nao gera PDF, mesmo padrao ja usado em
 * reports/maintenance-minimal.blade.php.
 */
trait HasPrintAction
{
    protected function printAction(): Action
    {
        return Action::make('imprimir')
            ->label('Imprimir')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->url(function () {
                $query = $this->getFilteredSortedTableQuery();
                $model = $query->getModel();
                $ids = $query->pluck($model->getKeyName())->all();

                $filtros = collect($this->getTable()->getFilters())
                    ->mapWithKeys(fn ($filter) => [$filter->getName() => $filter->getLabel()])
                    ->filter(fn ($label, $name) => filled($this->tableFilters[$name] ?? null))
                    ->map(function ($label, $name) {
                        $value = $this->tableFilters[$name];

                        if (is_array($value) && array_key_exists('values', $value)) {
                            // Filtro multiple(): 'values' é um array de opções selecionadas.
                            $raw = implode(', ', array_map('strval', (array) $value['values']));
                        } else {
                            $raw = is_array($value) ? ($value['value'] ?? $value['isActive'] ?? reset($value)) : $value;
                        }

                        if (is_bool($raw)) {
                            return "{$label}: ".($raw ? 'Sim' : 'Não');
                        }

                        return "{$label}: ".Str::of((string) $raw)->replace('_', ' ')->ucfirst();
                    })
                    ->values()
                    ->all();

                $token = (string) Str::uuid();

                Cache::put("table-print:{$token}", [
                    'model' => get_class($model),
                    'ids' => $ids,
                    'filtros' => $filtros,
                    'titulo' => static::getResource()::getPluralModelLabel(),
                ], now()->addMinutes(15));

                return route('table-print.show', ['token' => $token]);
            })
            ->openUrlInNewTab();
    }
}
