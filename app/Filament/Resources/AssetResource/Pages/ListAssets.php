<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\AssetExporter;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Services\AssetExcelImporter;
use App\Services\IntelligenceExportService;
use App\Support\AssetImport\AssetImportColumns;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ListAssets extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = AssetResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais: Total, um por status (cor por significado) e por tipo de ativo. */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'disponivel' => ['Disponível', ['status' => Asset::STATUS_DISPONIVEL], 'emerald'],
            'operando' => ['Em operação', ['status' => Asset::STATUS_OPERANDO], 'blue'],
            'locado' => ['Locado', ['status' => Asset::STATUS_LOCADO], 'amber'],
            'manutencao' => ['Em manutenção', ['status' => Asset::STATUS_MANUTENCAO], 'red'],
            'triagem' => ['Aguardando triagem', ['status' => Asset::STATUS_AGUARDANDO_TRIAGEM], 'orange'],
            'reservado' => ['Reservado', ['status' => Asset::STATUS_RESERVADO], 'pink'],
            'quarentena' => ['Quarentena', ['status' => Asset::STATUS_QUARENTENA], 'purple'],
            'veiculos' => ['Veículos', ['grupo' => Asset::GRUPO_VEICULO], 'indigo'],
            'maquinas' => ['Máquinas / equipamentos', ['grupo' => Asset::GRUPO_MAQUINA], 'teal'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Ativo'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(AssetExporter::class),
            $this->importExcelAction(),
            $this->templateAction(),
            $this->exportarParaIntelligenceAction(),
        ];
    }

    /** Sem widgets no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis acima da lista. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    /**
     * "Exportar para o Intelligence": gera a planilha de equipamentos do mês já preenchida com os dados do ERP (custo, retrabalho, dias
     * locado...). Só para quem tem o Intelligence (a mesma chave dos dados de equipamentos do contrato). Só leitura.
     */
    private function exportarParaIntelligenceAction(): Actions\Action
    {
        $meses = collect(range(1, 24))->mapWithKeys(fn ($i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => now()->startOfMonth()->subMonths($i)->translatedFormat('F/Y')])->all();

        return Actions\Action::make('exportarParaIntelligence')
            ->label('Exportar para o Intelligence')
            ->icon('heroicon-o-presentation-chart-line')
            ->color('gray')
            ->visible(fn () => (bool) auth()->user()?->can('viewAny', Asset::class) && (bool) Tenancy::current()?->hasFeature('tabela_intelligence_equipamentos'))
            ->modalHeading('Exportar equipamentos para o Intelligence')
            ->modalDescription('Gera o Excel do mês escolhido, já preenchido com custo de manutenção, corretivas, reincidências, dias locado e indisponível de cada equipamento. Depois é só importar em Intelligence › Importações.')
            ->modalSubmitActionLabel('Gerar planilha')
            ->form([
                Forms\Components\Select::make('mes')->label('Mês de referência')->options($meses)->default(array_key_first($meses))->required()->native(false)
                    ->helperText('Escolha um mês já fechado: os números são os do mês inteiro.'),
            ])
            ->action(function (array $data) {
                $tenant = Tenancy::current();
                if (! $tenant) {
                    Notification::make()->title('Selecione a empresa (tenant) antes de exportar.')->danger()->send();

                    return null;
                }
                $servico = app(IntelligenceExportService::class);
                $resultado = $servico->linhas($tenant->id, Carbon::createFromFormat('Y-m', $data['mes'])->startOfMonth());
                if ($resultado['linhas'] === []) {
                    Notification::make()->title('Nenhum equipamento para exportar')->body('Cadastre o Nº Patrimônio dos ativos para exportá-los.')->warning()->send();

                    return null;
                }
                Notification::make()->title(count($resultado['linhas']).' equipamentos exportados')
                    ->body($resultado['avisos'] ? implode('<br>', array_map('e', $resultado['avisos'])) : 'Planilha pronta para importar no Intelligence.')
                    ->color($resultado['avisos'] ? 'warning' : 'success')->persistent()->send();

                return response()->streamDownload(fn () => $servico->escrever($resultado, 'php://output'), 'intelligence-equipamentos-'.$data['mes'].'.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
            });
    }

    private function templateAction(): Actions\Action
    {
        return Actions\Action::make('baixarModeloImportacao')
            ->label('Modelo de importação (Excel)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn () => auth()->user()?->can('create', Asset::class))
            ->action(function () {
                return response()->streamDownload(
                    fn () => AssetImportColumns::writeTemplate('php://output'),
                    'modelo-importacao-ativos.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                );
            });
    }

    private function importExcelAction(): Actions\Action
    {
        return Actions\Action::make('importarExcel')
            ->label('Importar Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn () => auth()->user()?->can('create', Asset::class))
            ->modalHeading('Importar ativos de uma planilha Excel')
            ->modalDescription('Use o modelo de importação (botão ao lado). Linhas com erro são puladas e listadas no final; as demais são gravadas.')
            ->modalSubmitActionLabel('Importar')
            ->form([
                Forms\Components\FileUpload::make('arquivo')
                    ->label('Planilha (.xlsx)')
                    ->disk('local')
                    ->directory('asset-imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(10240)
                    ->required(),
                Forms\Components\Toggle::make('simular')
                    ->label('Apenas validar (não grava nada)')
                    ->helperText('Mostra o resultado e os erros sem criar nem alterar ativos. Recomendado na primeira vez.')
                    ->default(true),
                Forms\Components\Toggle::make('atualizar')
                    ->label('Atualizar ativos já cadastrados (mesmo Nº Patrimônio)')
                    ->helperText('Desligado: ativos que já existem são ignorados. Ligado: só as colunas preenchidas são atualizadas; células em branco não apagam nada.')
                    ->default(false),
            ])
            ->action(function (array $data) {
                $tenant = Tenancy::current();
                $path = Storage::disk('local')->path($data['arquivo']);

                try {
                    if (! $tenant) {
                        Notification::make()->title('Selecione a empresa (tenant) antes de importar.')->danger()->send();

                        return;
                    }
                    $r = app(AssetExcelImporter::class)->import($path, $tenant, (bool) $data['atualizar'], (bool) $data['simular']);
                } finally {
                    Storage::disk('local')->delete($data['arquivo']);
                }

                $errors = $r['errors'];
                $lines = array_map(fn ($e) => ($e['line'] ? "Linha {$e['line']}" : 'Planilha').($e['patrimonio'] !== '' ? " ({$e['patrimonio']})" : '').': '.e($e['message']), array_slice($errors, 0, 15));
                if (count($errors) > 15) {
                    $lines[] = '… e mais '.(count($errors) - 15).' erro(s).';
                }
                if ($r['dry_run']) {
                    $summary = "SIMULAÇÃO (nada foi gravado): {$r['created']} seriam criados, {$r['updated']} atualizados, {$r['skipped']} ignorados (já existem), ".count($errors).' com erro.';
                } else {
                    $summary = "{$r['created']} criados, {$r['updated']} atualizados, {$r['skipped']} ignorados (já existem), ".count($errors).' com erro.';
                }

                Notification::make()
                    ->title($summary)
                    ->body($lines ? implode('<br>', $lines) : null)
                    ->color($errors ? 'warning' : 'success')
                    ->persistent()
                    ->send();
            });
    }
}
