<?php

namespace App\Livewire;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Asset;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Services\Checklists\ChecklistAplicavel;
use App\Services\Checklists\ChecklistExecucaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Execucao do checklist do equipamento pelo celular (destino do botao do Dossie do ativo, que abre pelo QR Code). Mesma estrutura da
 * tela de checklist da Frota: poucos toques, foto so onde o item pede, voz nas observacoes e assinatura no fim. Item critico nao conforme
 * reprova o checklist (aptidao, OS e downtime: ver ChecklistExecucaoService).
 */
#[Layout('layouts.checklist-mobile')]
class ChecklistExecucaoMobile extends Component
{
    use WithFileUploads;

    public Asset $ativo;

    public ?string $templateId = null;

    public string $leitura = '';

    /** @var array<string, string> item_id => conforme|nao_conforme|na */
    public array $respostas = [];

    /** @var array<string, string> item_id => numero, texto ou opcao */
    public array $valores = [];

    /** @var array<string, string> */
    public array $observacoesItens = [];

    /** @var array<string, mixed> item_id => arquivo */
    public array $fotosItens = [];

    public string $observacoes = '';

    public ?string $assinatura = null;

    public string $iniciadoEm = '';

    public ?string $execucaoEnviadaId = null;

    /** OS do atendimento (vem do link da OS: ?os=<id>); so vale se e deste equipamento. */
    public ?string $osId = null;

    public function mount(string $assetId): void
    {
        $this->ativo = Asset::findOrFail($assetId);
        Gate::authorize('view', $this->ativo);
        Gate::authorize('create', CkExecution::class);

        // O link da OS manda ?checklist=<id>: so vale se o modelo se aplica a este ativo.
        $pedido = (string) request()->query('checklist', '');
        $this->templateId = $this->templates->contains('id', $pedido)
            ? $pedido
            : ($this->templates->count() === 1 ? $this->templates->first()->id : null);
        $this->iniciadoEm = now()->toIso8601String();

        $pedidoOs = (string) request()->query('os', '');
        $this->osId = $pedidoOs !== '' ? MaintenanceOrder::query()->where('asset_id', $this->ativo->id)->whereKey($pedidoOs)->value('id') : null;
    }

    /** @return Collection<int, CkTemplate> */
    public function getTemplatesProperty()
    {
        return app(ChecklistAplicavel::class)->paraAtivo($this->ativo);
    }

    public function getTemplateProperty(): ?CkTemplate
    {
        return $this->templateId ? $this->templates->firstWhere('id', $this->templateId) : null;
    }

    public function getItensProperty()
    {
        return $this->template ? app(ChecklistExecucaoService::class)->itensAtivos($this->template) : collect();
    }

    public function getExecucaoEnviadaProperty(): ?CkExecution
    {
        return $this->execucaoEnviadaId ? CkExecution::with('respostas')->find($this->execucaoEnviadaId) : null;
    }

    public function getOsDaExecucaoProperty(): ?MaintenanceOrder
    {
        $id = $this->execucaoEnviada?->ordem_servico_id;

        return $id ? MaintenanceOrder::withoutGlobalScopes()->find($id) : null;
    }

    /** Para onde voltar: a OS do atendimento (Modo Campo, etapa do checklist) ou, sem OS, o equipamento. */
    public function urlDeVolta(bool $desktop = false): string
    {
        if ($this->osId) {
            return $desktop
                ? MaintenanceOrderResource::getUrl('edit', ['record' => $this->osId])
                : route('maintenance-orders.field-wizard', ['maintenanceOrder' => $this->osId, 'step' => 2]);
        }

        return $desktop ? AssetResource::getUrl('edit', ['record' => $this->ativo]) : route('assets.dossier.mobile', ['assetId' => $this->ativo->id]);
    }

    public function podeAbrirOs(): bool
    {
        return Gate::allows('create', MaintenanceOrder::class);
    }

    public function updatedTemplateId(): void
    {
        $this->respostas = $this->valores = $this->observacoesItens = $this->fotosItens = [];
    }

    public function marcar(string $itemId, string $resposta): void
    {
        if (in_array($resposta, [ChecklistExecucaoService::RESPOSTA_CONFORME, ChecklistExecucaoService::RESPOSTA_NAO_CONFORME, ChecklistExecucaoService::RESPOSTA_NA], true)) {
            $this->respostas[$itemId] = $resposta;
        }
    }

    public function salvarAssinatura(string $dataUrl): void
    {
        if (! str_starts_with($dataUrl, 'data:image')) {
            $this->addError('assinatura', 'Assinatura inválida.');

            return;
        }

        $this->assinatura = $dataUrl;
        $this->resetErrorBag('assinatura');
    }

    public function enviar(): void
    {
        $this->resetErrorBag();

        if (! $this->template) {
            $this->addError('itens', 'Escolha o checklist.');

            return;
        }

        $this->validate([
            'fotosItens.*' => 'nullable|image|max:10240',
            'leitura' => 'nullable|numeric|min:0',
        ], ['fotosItens.*.image' => 'O arquivo precisa ser uma imagem.', 'leitura.numeric' => 'A leitura do horímetro/km deve ser um número.']);

        $dados = ['respostas' => [], 'assinatura' => $this->assinatura, 'observacoes' => $this->observacoes, 'iniciado_em' => $this->iniciadoEm ?: null, 'os_id' => $this->osId];

        foreach ($this->itens as $item) {
            $dados['respostas'][$item->id] = [
                'resposta' => $this->respostas[$item->id] ?? null,
                'valor_numerico' => $item->tipo_resposta === 'numerico' ? ($this->valores[$item->id] ?? null) : null,
                'valor_texto' => in_array($item->tipo_resposta, ['texto', 'selecao'], true) ? ($this->valores[$item->id] ?? null) : null,
                'foto' => $this->fotosItens[$item->id] ?? null,
                'observacao' => $this->observacoesItens[$item->id] ?? null,
            ];
        }

        if (filled($this->leitura)) {
            $dados['leitura_medidor'] = $this->leitura;
            $dados['medidor'] = $this->ativo->category?->medidor === 'km' ? 'km' : 'horimetro';
        }

        try {
            $execucao = app(ChecklistExecucaoService::class)->registrar($this->ativo, $this->template, $dados, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensagens) {
                $this->addError($campo === 'template' ? 'itens' : $campo, $mensagens[0]);
            }

            return;
        }

        $this->execucaoEnviadaId = $execucao->id;
    }

    public function abrirOs(): void
    {
        abort_unless($this->podeAbrirOs() && $this->execucaoEnviada, 403);

        try {
            app(ChecklistExecucaoService::class)->abrirOsCorretiva($this->execucaoEnviada);
        } catch (ValidationException $e) {
            $this->addError('os', $e->errors()['os'][0] ?? 'Não foi possível abrir a OS.');
        }
    }

    public function render()
    {
        return view('livewire.checklist-execucao-mobile');
    }
}
