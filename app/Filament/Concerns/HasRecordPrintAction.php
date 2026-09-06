<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * Botao "Imprimir" para paginas de detalhe (ViewRecord) de qualquer
 * Filament Resource -- versao "irma" de HasPrintAction (que imprime a
 * LISTAGEM filtrada de uma tabela). Aqui imprime o registro atual sendo
 * visualizado, reaproveitando a mesma view HTML minimalista (via
 * GenericRecordPrintController -> reports.table-print) e o mesmo
 * mapeamento de colunas por Model (TablePrintController::columnsFor()) --
 * nao gera PDF, mesmo padrao "pronto pra Ctrl+P" das outras impressoes.
 */
trait HasRecordPrintAction
{
    protected function printAction(): Action
    {
        return Action::make('imprimir')
            ->label('Imprimir')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->url(fn () => route('generic-record.print', [
                'resource' => static::getResource()::getSlug(),
                'record' => $this->record->getKey(),
            ]))
            ->openUrlInNewTab();
    }
}
