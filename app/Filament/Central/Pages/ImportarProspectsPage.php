<?php

namespace App\Filament\Central\Pages;

use App\Models\ProspectImportacao;
use App\Services\CrmImport\RelatorioImportacao;
use App\Services\ProspectImport\ProspectImportLeitor;
use App\Services\ProspectImport\ProspectImportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importar lista de prospects (CSV, Excel ou JSON) na Central, em 4 passos como o importador do CRM:
 * arquivo -> ligar colunas -> SIMULACAO obrigatoria (nao grava) -> resultado com relatorio.
 * Identifica a empresa pelo CNPJ; nunca apaga dado nem mexe no que o comercial ja preencheu.
 * Substitui o comando `prospects:importar` para o uso do dia a dia (o comando continua existindo).
 */
class ImportarProspectsPage extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $title = 'Importar lista de prospects';

    protected static ?string $slug = 'importar-prospects';

    protected static string $view = 'filament.central.pages.importar-prospects';

    /** O acesso e' pelo botao "Importar lista" da tela de Prospects, nao pelo menu. */
    protected static bool $shouldRegisterNavigation = false;

    /** 1 arquivo, 2 colunas, 3 simulacao, 4 resultado */
    public int $passo = 1;

    public $arquivo = null;

    public ?string $caminho = null;

    public ?string $nomeArquivo = null;

    /** @var array<int, string> */
    public array $cabecalhos = [];

    public array $previa = [];

    public int $totalLinhas = 0;

    /** campo => indice da coluna (ou vazio) */
    public array $mapeamento = [];

    public array $opcoes = ['se_existir' => ProspectImportService::SE_COMPLETAR, 'origem' => '', 'lote' => ''];

    public ?array $simulacao = null;

    public ?string $hashSimulado = null;

    public ?array $resultado = null;

    public ?string $relatorioCsv = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function getCampos(): array
    {
        return ProspectImportService::CAMPOS;
    }

    public function updatedMapeamento(): void
    {
        $this->simulacao = null;
        $this->hashSimulado = null;
    }

    public function updatedOpcoes(): void
    {
        $this->simulacao = null;
        $this->hashSimulado = null;
    }

    public function lerArquivo(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate(['arquivo' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,json']]);

        /** @var TemporaryUploadedFile $arquivo */
        $arquivo = $this->arquivo;
        $extensao = strtolower($arquivo->getClientOriginalExtension() ?: 'csv');
        $this->apagarArquivos();
        $this->caminho = $arquivo->storeAs('prospect-imports', Str::uuid().'.'.$extensao, 'local');
        $this->nomeArquivo = $arquivo->getClientOriginalName();

        try {
            $lido = app(ProspectImportLeitor::class)->ler(Storage::disk('local')->path($this->caminho), $extensao);
        } catch (\DomainException $e) {
            $this->apagarArquivos();
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->cabecalhos = $lido['cabecalhos'];
        $this->totalLinhas = count($lido['linhas']);
        $this->previa = array_slice(array_values($lido['linhas']), 0, 5);
        $this->mapeamento = ProspectImportService::sugerirMapeamento($this->cabecalhos);
        $this->opcoes['origem'] = $this->opcoes['origem'] ?: Str::limit(pathinfo((string) $this->nomeArquivo, PATHINFO_FILENAME), 100, '');
        $this->simulacao = null;
        $this->hashSimulado = null;
        $this->passo = 2;
    }

    public function simular(): void
    {
        $relatorio = $this->executar(simular: true);

        if (! $relatorio) {
            return;
        }

        $this->simulacao = ['totais' => $relatorio->totais(), 'linhas' => $relatorio->linhasParaExibir(50)];
        $this->hashSimulado = $this->hashAtual();
        $this->guardarRelatorio($relatorio);
        $this->passo = 3;
    }

    public function importar(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->hashSimulado === null || $this->hashSimulado !== $this->hashAtual()) {
            Notification::make()->title('Faça a simulação antes de importar (ela precisa ser refeita se você mudou algo).')->warning()->send();

            return;
        }

        try {
            $relatorio = $this->executar(simular: false);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('A importação falhou e nada foi gravado. Tente de novo ou peça ajuda.')->danger()->send();

            return;
        }

        if (! $relatorio) {
            return;
        }

        ProspectImportacao::create([
            'user_id' => auth()->id(),
            'arquivo' => (string) $this->nomeArquivo,
            'origem' => $this->opcoes['origem'] ?: null,
            'lote' => filled($this->opcoes['lote']) ? (int) $this->opcoes['lote'] : null,
            'se_existir' => $this->opcoes['se_existir'],
            ...$relatorio->totais(),
            'relatorio' => $relatorio->paraHistorico(),
        ]);

        $this->resultado = ['totais' => $relatorio->totais(), 'linhas' => $relatorio->linhasParaExibir(50)];
        $this->guardarRelatorio($relatorio);
        $this->apagarArquivoLista();
        $this->simulacao = null;
        $this->hashSimulado = null;
        $this->passo = 4;

        Notification::make()->title("Importação concluída: {$relatorio->criados} criados, {$relatorio->atualizados} atualizados, {$relatorio->ignorados} ignorados, {$relatorio->erros} com erro.")->success()->send();
    }

    public function voltar(int $passo): void
    {
        $this->passo = max(1, min($passo, $this->passo));
    }

    public function recomecar(): void
    {
        $this->apagarArquivos();
        $this->reset(['passo', 'arquivo', 'caminho', 'nomeArquivo', 'cabecalhos', 'previa', 'totalLinhas', 'mapeamento', 'simulacao', 'hashSimulado', 'resultado']);
        $this->opcoes = ['se_existir' => ProspectImportService::SE_COMPLETAR, 'origem' => '', 'lote' => ''];
    }

    public function baixarModelo()
    {
        abort_unless(static::canAccess(), 403);

        return response()->streamDownload(
            fn () => print ProspectImportService::modeloCsv(),
            'modelo-importacao-prospects.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function baixarRelatorio()
    {
        abort_unless(static::canAccess() && $this->relatorioCsv && Storage::disk('local')->exists($this->relatorioCsv), 404);

        return Storage::disk('local')->download($this->relatorioCsv, 'relatorio-importacao-prospects-'.now()->format('Ymd-His').'.csv');
    }

    public function getHistorico()
    {
        return ProspectImportacao::with('usuario')->latest('created_at')->limit(8)->get();
    }

    private function executar(bool $simular): ?RelatorioImportacao
    {
        if (! static::canAccess() || ! $this->caminho) {
            Notification::make()->title('Envie o arquivo novamente.')->danger()->send();

            return null;
        }

        if (blank($this->mapeamento['cnpj'] ?? null)) {
            Notification::make()->title('Ligue a coluna do CNPJ: é por ele que a empresa é identificada.')->warning()->send();

            return null;
        }

        if (blank($this->mapeamento['nome'] ?? null) && blank($this->mapeamento['razao_social'] ?? null)) {
            Notification::make()->title('Ligue a coluna do nome ou da razão social.')->warning()->send();

            return null;
        }

        try {
            $lido = app(ProspectImportLeitor::class)->ler(Storage::disk('local')->path($this->caminho));
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível reler o arquivo. Envie de novo.')->danger()->send();

            return null;
        }

        return app(ProspectImportService::class)->processar($lido['linhas'], $this->mapeamento, $this->opcoes, $simular);
    }

    private function hashAtual(): string
    {
        return md5(json_encode([$this->caminho, $this->mapeamento, $this->opcoes]));
    }

    private function guardarRelatorio(RelatorioImportacao $relatorio): void
    {
        $anterior = $this->relatorioCsv;
        $this->relatorioCsv = 'prospect-imports/relatorio-'.Str::uuid().'.csv';
        Storage::disk('local')->put($this->relatorioCsv, $relatorio->paraCsv());

        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }
    }

    private function apagarArquivoLista(): void
    {
        if ($this->caminho) {
            Storage::disk('local')->delete($this->caminho);
            $this->caminho = null;
        }
    }

    private function apagarArquivos(): void
    {
        $this->apagarArquivoLista();

        if ($this->relatorioCsv) {
            Storage::disk('local')->delete($this->relatorioCsv);
            $this->relatorioCsv = null;
        }
    }
}
