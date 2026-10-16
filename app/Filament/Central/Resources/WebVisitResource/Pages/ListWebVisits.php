<?php

namespace App\Filament\Central\Resources\WebVisitResource\Pages;

use App\Filament\Central\Resources\WebVisitResource;
use App\Support\WebVisitExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWebVisits extends ListRecords
{
    protected static string $resource = WebVisitResource::class;

    /** Exporta o que está na tela: respeita filtros e busca atuais. */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportarExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(function () {
                    $path = WebVisitExport::xlsx($this->getFilteredSortedTableQuery());

                    return response()->download($path, 'visitas-site-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
                }),
            Actions\Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    // dompdf gasta ~1 MB por visita (limite do PHP em PROD: 128M).
                    @ini_set('memory_limit', '512M');
                    @set_time_limit(120);

                    [$visitas, $total] = WebVisitExport::pdfData($this->getFilteredSortedTableQuery());
                    $pdf = Pdf::loadView('pdf.visitas-site', compact('visitas', 'total'))->setPaper('a3', 'landscape');

                    return response()->streamDownload(fn () => print ($pdf->output()), 'visitas-site-'.now()->format('Ymd-His').'.pdf');
                }),
        ];
    }
}
