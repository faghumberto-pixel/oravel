<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChecklist;
use App\Models\FrotaItemModeloChecklist as Item;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaRespostaChecklist as Resposta;
use App\Services\Frota\ChecklistFrotaService;
use App\Services\Frota\ModelosChecklistPadrao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Checklist de saída / retorno do veículo, pelo celular (destino do botão no Dossiê do ativo, que abre pelo QR
 * Code). Poucos toques: OK / Problema / Não se aplica por item, foto só onde o item exige, 4 fotos das laterais e
 * assinatura. Item crítico com problema bloqueia o veículo (ver ChecklistFrotaService).
 */
#[Layout('layouts.checklist-mobile')]
class ChecklistFrotaMobile extends Component
{
    use WithFileUploads;

    public Asset $ativo;

    public string $tipo = FrotaChecklist::TIPO_SAIDA;

    public ?string $modeloId = null;

    public ?string $motoristaId = null;

    public string $odometro = '';

    public string $justificativaOdometro = '';

    public string $combustivel = '';

    /** @var array<string, string> item_id => ok|problema|nao_se_aplica */
    public array $resultados = [];

    /** @var array<string, string> */
    public array $valores = [];

    /** @var array<string, string> */
    public array $observacoesItens = [];

    /** @var array<string, mixed> item_id => arquivo */
    public array $fotosProblema = [];

    public $fotoFrente = null;

    public $fotoTraseira = null;

    public $fotoEsquerda = null;

    public $fotoDireita = null;

    public string $observacoes = '';

    public ?string $assinatura = null;

    public ?string $checklistEnviadoId = null;

    public function mount(string $assetId): void
    {
        $this->ativo = Asset::findOrFail($assetId);
        Gate::authorize('view', $this->ativo);
        Gate::authorize('create', FrotaChecklist::class);

        abort_unless($this->ativo->isVehicle(), 404, 'Este ativo não é um veículo.');

        ModelosChecklistPadrao::garantir($this->ativo->tenant_id);

        $this->modeloId = $this->modelosDisponiveis()->firstWhere('tipo', FrotaModeloChecklist::TIPO_RAPIDO)?->id
            ?? $this->modelosDisponiveis()->first()?->id;
        $this->odometro = (string) (int) floor((float) $this->ativo->odometro_atual);
    }

    /** @return Collection<int, FrotaModeloChecklist> */
    public function modelosDisponiveis()
    {
        return FrotaModeloChecklist::query()->where('ativo', true)->orderBy('tipo')->orderBy('nome')->get()
            ->filter(fn (FrotaModeloChecklist $m) => $m->serveParaVeiculo($this->ativo))->values();
    }

    public function getItensProperty()
    {
        return $this->modeloId ? Item::where('modelo_id', $this->modeloId)->orderBy('ordem')->get() : collect();
    }

    public function getMotoristasProperty()
    {
        return FleetDriver::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function updatedModeloId(): void
    {
        $this->resultados = $this->valores = $this->observacoesItens = $this->fotosProblema = [];
    }

    public function marcar(string $itemId, string $resultado): void
    {
        if (in_array($resultado, [Resposta::OK, Resposta::PROBLEMA, Resposta::NAO_SE_APLICA], true)) {
            $this->resultados[$itemId] = $resultado;
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
        $itens = $this->itens;

        $this->validate([
            'modeloId' => 'required',
            'odometro' => 'required|integer|min:0',
            'fotoFrente' => 'required|image|max:10240',
            'fotoTraseira' => 'required|image|max:10240',
            'fotoEsquerda' => 'required|image|max:10240',
            'fotoDireita' => 'required|image|max:10240',
            'assinatura' => 'required',
        ], [
            'odometro.required' => 'Informe o odômetro (km).',
            'odometro.integer' => 'O odômetro deve ser um número inteiro, em km.',
            'fotoFrente.required' => 'Tire a foto da frente.',
            'fotoTraseira.required' => 'Tire a foto da traseira.',
            'fotoEsquerda.required' => 'Tire a foto do lado esquerdo.',
            'fotoDireita.required' => 'Tire a foto do lado direito.',
            '*.image' => 'O arquivo precisa ser uma imagem.',
            'assinatura.required' => 'Assine na caixa de assinatura e toque em "Confirmar assinatura".',
        ]);

        foreach ($itens as $item) {
            $resultado = $this->resultados[$item->id] ?? null;
            if (! $resultado) {
                $this->addError('itens', "Responda o item: {$item->descricao}.");

                return;
            }
            if ($item->tipo_resposta === Item::RESPOSTA_NUMERO && $resultado === Resposta::OK && blank($this->valores[$item->id] ?? null)) {
                $this->addError('itens', "Informe o valor medido: {$item->descricao}.");

                return;
            }
            if ($resultado === Resposta::PROBLEMA && $item->exige_foto_se_problema && empty($this->fotosProblema[$item->id])) {
                $this->addError('itens', "Tire a foto do problema: {$item->descricao}.");

                return;
            }
        }

        try {
            $checklist = app(ChecklistFrotaService::class)->registrar($this->ativo, [
                'tipo' => $this->tipo,
                'modelo_id' => $this->modeloId,
                'motorista_id' => $this->motoristaId ?: null,
                'odometro' => (int) $this->odometro,
                'justificativa_odometro' => $this->justificativaOdometro ?: null,
                'nivel_combustivel' => $this->combustivel ?: null,
                'assinatura' => $this->assinatura,
                'observacoes' => $this->observacoes ?: null,
                'respostas' => collect($itens)->mapWithKeys(fn ($item) => [$item->id => [
                    'resultado' => $this->resultados[$item->id],
                    'valor' => $this->valores[$item->id] ?? null,
                    'observacao' => $this->observacoesItens[$item->id] ?? null,
                ]])->all(),
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensagens) {
                $this->addError($campo === 'odometro' ? 'odometro' : 'itens', $mensagens[0]);
            }

            return;
        }

        foreach (['frente' => $this->fotoFrente, 'traseira' => $this->fotoTraseira, 'esquerda' => $this->fotoEsquerda, 'direita' => $this->fotoDireita] as $lado => $arquivo) {
            $checklist->addMedia($arquivo->getRealPath())->usingFileName("{$lado}-".uniqid().'.jpg')->withCustomProperties(['lado' => $lado])->toMediaCollection('laterais');
        }
        foreach ($this->fotosProblema as $itemId => $arquivo) {
            if ($arquivo) {
                $checklist->addMedia($arquivo->getRealPath())->usingFileName('problema-'.uniqid().'.jpg')->withCustomProperties(['item_id' => $itemId])->toMediaCollection('problemas');
            }
        }

        $this->checklistEnviadoId = $checklist->id;
    }

    public function getChecklistEnviadoProperty(): ?FrotaChecklist
    {
        return $this->checklistEnviadoId ? FrotaChecklist::find($this->checklistEnviadoId) : null;
    }

    public function render()
    {
        return view('livewire.checklist-frota-mobile', [
            'modelos' => $this->modelosDisponiveis(),
            'completoVencido' => $this->ativo->checklistCompletoVencido(),
        ]);
    }
}
