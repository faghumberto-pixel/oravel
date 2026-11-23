<?php

namespace App\Filament\Resources\IntelligenceImportResource\Pages;

use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Services\AnaliseDeEquipamentos;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\ModeloExcel;
use App\Filament\Resources\IntelligenceImportResource;
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
                fn () => ModeloExcel::escrever('php://output'),
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
                $linhas = [];
                if ($geral) {
                    $linhas[] = 'Índice geral: '.number_format($geral['pontuacao'], 0).' ('.$geral['faixa'].')';
                }
                if ($r['equipamentos'] > 0) {
                    $rot = AnaliseDeEquipamentos::ROTULOS;
                    $linhas[] = $r['equipamentos'].' equipamentos analisados: '.collect($r['classificacoes'])->map(fn ($n, $k) => $n.' '.mb_strtolower($rot[$k] ?? $k))->implode(', ').'.';
                }
                foreach ($r['avisos'] as $aviso) {
                    $linhas[] = 'Aviso: '.e($aviso);
                }
                Notification::make()
                    ->title(($r['simulacao'] ? 'SIMULAÇÃO (nada foi gravado): ' : 'Importação concluída: ').count($r['indicadores']).' indicadores calculados')
                    ->body($linhas ? implode('<br>', $linhas) : null)
                    ->success()->persistent()->send();
            });
    }
}
