<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\AssetExporter;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Services\AssetExcelImporter;
use App\Support\AssetImport\AssetImportColumns;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListAssets extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Ativo'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(AssetExporter::class),
            $this->importExcelAction(),
            $this->templateAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AssetResource\Widgets\AssetStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 2;
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
