<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\CrmImportacao;
use App\Models\CrmLead;
use App\Services\CrmImport\CrmImportDestinos;
use App\Services\CrmImport\CrmImportLeitor;
use App\Services\CrmImport\CrmImportService;
use App\Services\CrmImport\RelatorioImportacao;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importação de planilha (CSV/XLSX) para Leads, Clientes e Contatos, com
 * mapeamento de colunas na tela, simulação obrigatória antes de gravar e
 * relatório do que foi criado/atualizado/ignorado.
 */
class ImportarPlanilhaCrm extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Importar Planilha';

    protected static ?string $title = 'Importar Planilha (CRM)';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.importar-planilha-crm';

    /** 1 arquivo, 2 mapeamento, 3 simulação, 4 resultado */
    public int $passo = 1;

    public string $destino = CrmImportDestinos::LEAD;

    public $planilha = null;

    public ?string $caminho = null;

    public ?string $nomeArquivo = null;

    /** @var array<int, string> */
    public array $cabecalhos = [];

    public array $previa = [];

    public int $totalLinhas = 0;

    /** campo => índice da coluna (ou null) */
    public array $mapeamento = [];

    public array $opcoes = ['se_existir' => 'ignorar', 'pai' => 'cliente'];

    public ?array $simulacao = null;

    public ?string $hashSimulado = null;

    public ?array $resultado = null;

    public ?string $relatorioCsv = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('viewAny', CrmLead::class) || $user?->can('viewAny', Client::class));
    }

    /** @return array<string, string> destinos que o usuário pode alimentar */
    public function getDestinosPermitidos(): array
    {
        $user = auth()->user();
        $podeLead = (bool) $user?->can('create', CrmLead::class);
        $podeCliente = (bool) $user?->can('create', Client::class);

        return collect(CrmImportDestinos::rotulos())->filter(fn ($r, $d) => match ($d) {
            CrmImportDestinos::LEAD => $podeLead,
            CrmImportDestinos::CLIENTE => $podeCliente,
            default => $podeLead || $podeCliente,
        })->all();
    }

    public function getCampos(): array
    {
        return CrmImportDestinos::campos($this->destino, (string) Tenancy::current()?->id);
    }

    public function updatedDestino(): void
    {
        $this->recomecar(manterDestino: true);
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

    public function lerPlanilha(): void
    {
        if (! array_key_exists($this->destino, $this->getDestinosPermitidos())) {
            Notification::make()->title('Você não tem permissão para importar nesse destino.')->danger()->send();

            return;
        }

        $this->validate(['planilha' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx']]);

        $tenant = Tenancy::current();
        if (! $tenant) {
            Notification::make()->title('Selecione a empresa (tenant) antes de importar.')->danger()->send();

            return;
        }

        /** @var TemporaryUploadedFile $arquivo */
        $arquivo = $this->planilha;
        $extensao = strtolower($arquivo->getClientOriginalExtension() ?: 'csv');
        $this->apagarArquivos();
        $this->caminho = $arquivo->storeAs("crm-imports/{$tenant->id}", Str::uuid().'.'.$extensao, 'local');
        $this->nomeArquivo = $arquivo->getClientOriginalName();

        try {
            $lido = app(CrmImportLeitor::class)->ler(Storage::disk('local')->path($this->caminho), $extensao);
        } catch (\DomainException $e) {
            $this->apagarArquivos();
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->cabecalhos = $lido['cabecalhos'];
        $this->totalLinhas = count($lido['linhas']);
        $this->previa = array_slice(array_values($lido['linhas']), 0, 5);
        $this->mapeamento = CrmImportDestinos::sugerirMapeamento($this->cabecalhos, $this->destino, (string) $tenant->id);
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
        if ($this->hashSimulado === null || $this->hashSimulado !== $this->hashAtual()) {
            Notification::make()->title('Faça a simulação antes de importar (ela precisa ser refeita se você mudou algo).')->warning()->send();

            return;
        }

        $relatorio = $this->executar(simular: false);

        if (! $relatorio) {
            return;
        }

        CrmImportacao::create([
            'tenant_id' => Tenancy::current()->id,
            'user_id' => auth()->id(),
            'destino' => $this->destino,
            'arquivo' => (string) $this->nomeArquivo,
            ...$relatorio->totais(),
            'relatorio' => $relatorio->paraHistorico(),
        ]);

        $this->resultado = ['totais' => $relatorio->totais(), 'linhas' => $relatorio->linhasParaExibir(50)];
        $this->guardarRelatorio($relatorio);
        $this->apagarArquivoPlanilha();
        $this->simulacao = null;
        $this->hashSimulado = null;
        $this->passo = 4;

        Notification::make()->title("Importação concluída: {$relatorio->criados} criados, {$relatorio->atualizados} atualizados, {$relatorio->ignorados} ignorados, {$relatorio->erros} com erro.")->success()->send();
    }

    public function voltar(int $passo): void
    {
        $this->passo = max(1, min($passo, $this->passo));
    }

    public function recomecar(bool $manterDestino = false): void
    {
        $this->apagarArquivos();
        $destino = $this->destino;
        $this->reset(['passo', 'planilha', 'caminho', 'nomeArquivo', 'cabecalhos', 'previa', 'totalLinhas', 'mapeamento', 'simulacao', 'hashSimulado', 'resultado']);
        $this->opcoes = ['se_existir' => 'ignorar', 'pai' => 'cliente'];
        $this->destino = $manterDestino ? $destino : CrmImportDestinos::LEAD;
    }

    public function baixarModelo(string $destino)
    {
        abort_unless(array_key_exists($destino, $this->getDestinosPermitidos()), 403);

        return response()->streamDownload(
            fn () => print CrmImportDestinos::modeloCsv($destino, (string) Tenancy::current()?->id),
            "modelo-importacao-{$destino}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function baixarRelatorio()
    {
        abort_unless($this->relatorioCsv && Storage::disk('local')->exists($this->relatorioCsv), 404);

        return Storage::disk('local')->download($this->relatorioCsv, 'relatorio-importacao-'.now()->format('Ymd-His').'.csv');
    }

    public function getHistorico()
    {
        return CrmImportacao::with('usuario')->latest()->limit(8)->get();
    }

    private function executar(bool $simular): ?RelatorioImportacao
    {
        $tenant = Tenancy::current();

        if (! $tenant || ! $this->caminho || ! array_key_exists($this->destino, $this->getDestinosPermitidos())) {
            Notification::make()->title('Envie a planilha novamente.')->danger()->send();

            return null;
        }

        $faltando = collect($this->getCampos())->filter(fn ($d, $c) => $d['obrigatorio'] && ($this->mapeamento[$c] ?? null) === null)->pluck('rotulo');

        // Lead aceita "empresa" no lugar do nome; contato precisa de algo que o ligue ao cliente/lead.
        if ($this->destino === CrmImportDestinos::LEAD && ($this->mapeamento['company_name'] ?? null) !== null) {
            $faltando = $faltando->reject(fn ($r) => $r === 'Nome do contato');
        }

        if ($faltando->isNotEmpty()) {
            Notification::make()->title('Mapeie os campos obrigatórios: '.$faltando->implode(', ').'.')->warning()->send();

            return null;
        }

        if ($this->destino === CrmImportDestinos::CONTATO && ($this->mapeamento['pai_documento'] ?? null) === null && ($this->mapeamento['pai_nome'] ?? null) === null) {
            Notification::make()->title('Mapeie o CNPJ/CPF ou o nome do cliente (ou lead) a que cada contato pertence.')->warning()->send();

            return null;
        }

        try {
            $lido = app(CrmImportLeitor::class)->ler(Storage::disk('local')->path($this->caminho));
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível reler a planilha. Envie de novo.')->danger()->send();

            return null;
        }

        return app(CrmImportService::class)->processar($this->destino, $lido['linhas'], $this->mapeamento, $this->opcoes, $tenant, $simular);
    }

    private function hashAtual(): string
    {
        return md5(json_encode([$this->destino, $this->caminho, $this->mapeamento, $this->opcoes]));
    }

    private function guardarRelatorio(RelatorioImportacao $relatorio): void
    {
        $tenantId = Tenancy::current()?->id;
        $anterior = $this->relatorioCsv;
        $this->relatorioCsv = "crm-imports/{$tenantId}/relatorio-".Str::uuid().'.csv';
        Storage::disk('local')->put($this->relatorioCsv, $relatorio->paraCsv());

        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }
    }

    private function apagarArquivoPlanilha(): void
    {
        if ($this->caminho) {
            Storage::disk('local')->delete($this->caminho);
            $this->caminho = null;
        }
    }

    private function apagarArquivos(): void
    {
        $this->apagarArquivoPlanilha();

        if ($this->relatorioCsv) {
            Storage::disk('local')->delete($this->relatorioCsv);
            $this->relatorioCsv = null;
        }
    }
}
