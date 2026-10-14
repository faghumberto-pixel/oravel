<?php

namespace App\Filament\Central\Resources\TreinamentoResultadoResource\Pages;

use App\Filament\Central\Resources\TreinamentoResultadoResource;
use App\Models\TreinamentoResultado;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreinamentoResultados extends ListRecords
{
    protected static string $resource = TreinamentoResultadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar_csv')
                ->label('Exportar CSV (filtro atual)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $registros = $this->getFilteredSortedTableQuery()->with('tenant')->get();

                    return response()->streamDownload(function () use ($registros) {
                        $saida = fopen('php://output', 'w');
                        fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel abrir em UTF-8
                        fputcsv($saida, ['Data', 'Nome', 'Função', 'Empresa', 'Cliente cadastrado', 'Nota', 'Acertos', 'Total', 'Situação', 'Treinamento'], ';');

                        /** @var TreinamentoResultado $r */
                        foreach ($registros as $r) {
                            fputcsv($saida, [
                                $r->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                                $r->nome,
                                $r->funcao,
                                $r->empresa,
                                $r->tenant?->name,
                                number_format((float) $r->nota, 1, ',', ''),
                                $r->acertos,
                                $r->total,
                                $r->aprovado ? 'Aprovado' : 'Precisa refazer',
                                $r->treinamento,
                            ], ';');
                        }

                        fclose($saida);
                    }, 'resultados-treinamento-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
