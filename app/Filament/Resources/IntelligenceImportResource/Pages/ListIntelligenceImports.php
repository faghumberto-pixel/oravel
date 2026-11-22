<?php

namespace App\Filament\Resources\IntelligenceImportResource\Pages;

use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\Catalogo;
use App\Filament\Resources\IntelligenceImportResource;
use App\Support\AssetImport\AssetImportColumns;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListIntelligenceImports extends ListRecords
{
    protected static string $resource = IntelligenceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->modeloAction(), $this->importarAction()];
    }

    private function modeloAction(): Actions\Action
    {
        return Actions\Action::make('modelo')
            ->label('Modelo de importação (Excel)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn () => auth()->user()?->can('viewAny', IntelligenceImport::class))
            ->action(fn () => response()->streamDownload(
                fn () => AssetImportColumns::writeTemplate('php://output', Catalogo::colunasDoModelo(), Catalogo::SHEET, [
                    'Modelo de importação do Oravel Intelligence',
                    'Preencha a aba "Dados" a partir da linha 3, com UMA linha: a fotografia da empresa na data de referência. Não altere os títulos das colunas.',
                    'Só "Data de Referência" é obrigatória, mas quanto mais campos preenchidos, mais indicadores e áreas do diagnóstico são calculados.',
                    'Para acompanhar a evolução, envie uma planilha nova a cada período. Nada que já foi importado é alterado.',
                    'Números com ponto ou vírgula decimal, sem R$ nem %.',
                    '',
                ], true),
                'modelo-importacao-intelligence.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ));
    }

    private function importarAction(): Actions\Action
    {
        return Actions\Action::make('importar')
            ->label('Importar Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => auth()->user()?->can('viewAny', IntelligenceImport::class))
            ->modalHeading('Importar a fotografia da empresa')
            ->modalDescription('Cada importação gera um snapshot novo e permanece guardada no histórico. Nada é sobrescrito.')
            ->modalSubmitActionLabel('Importar')
            ->form([
                Forms\Components\FileUpload::make('arquivo')->label('Planilha (.xlsx)')->disk('local')->directory('intelligence-imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240)->required(),
                Forms\Components\Toggle::make('simular')->label('Apenas validar (não grava nada)')
                    ->helperText('Mostra se a planilha está correta e quantos indicadores seriam calculados.')->default(true),
            ])
            ->action(function (array $data) {
                $tenant = Tenancy::current();
                $path = Storage::disk('local')->path($data['arquivo']);

                try {
                    if (! $tenant) {
                        Notification::make()->title('Selecione a empresa (tenant) antes de importar.')->danger()->send();

                        return;
                    }
                    $r = app(ImportadorExcel::class)->importar($path, $tenant, auth()->user(), (bool) $data['simular'], basename($data['arquivo']));
                } finally {
                    Storage::disk('local')->delete($data['arquivo']);
                }

                if (! $r['ok']) {
                    Notification::make()->title('Importação não realizada')->body(implode('<br>', array_map('e', array_slice($r['erros'], 0, 15))))
                        ->danger()->persistent()->send();

                    return;
                }
                $geral = $r['diagnostico']['geral'] ?? null;
                Notification::make()
                    ->title(($r['simulacao'] ? 'SIMULAÇÃO (nada foi gravado): ' : 'Importação concluída: ').count($r['indicadores']).' indicadores calculados')
                    ->body($geral ? 'Índice geral: '.number_format($geral['pontuacao'], 0).' ('.$geral['faixa'].')' : null)
                    ->success()->persistent()->send();
            });
    }
}
