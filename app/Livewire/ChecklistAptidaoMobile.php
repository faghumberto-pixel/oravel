<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\CatalogoItem;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Services\Catalogo\Aptidao\AptidaoImpacto;
use App\Services\Catalogo\Aptidao\ChecklistAptidaoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** G1 fase 5 -- checklist do grupo do equipamento no celular do tecnico (itens IMP/ALR). Mesmo padrao visual da OS rapida. */
#[Layout('layouts.checklist-mobile')]
class ChecklistAptidaoMobile extends Component
{
    public string $busca = '';

    public ?string $assetId = null;

    /** @var array<string, string> requisito_id => ok|reprovado */
    public array $resultados = [];

    /** @var array<string, string> requisito_id => observacao */
    public array $observacoes = [];

    public ?array $resumo = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', CatalogoItem::class);
        abort_unless(AptidaoGate::ligada(auth()->user()?->tenant_id), 404);
    }

    public function getEquipamentosProperty()
    {
        return Asset::query()
            ->when(trim($this->busca) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', '%'.trim($this->busca).'%')
                ->orWhere('tag', 'ilike', '%'.trim($this->busca).'%')
                ->orWhere('patrimonio', 'ilike', '%'.trim($this->busca).'%')))
            ->orderBy('name')->limit(40)->get(['id', 'name', 'tag', 'patrimonio', 'asset_category_id', 'tenant_id']);
    }

    public function getAtivoProperty(): ?Asset
    {
        return $this->assetId ? Asset::query()->find($this->assetId) : null;
    }

    public function getItensProperty()
    {
        return $this->ativo ? app(ChecklistAptidaoService::class)->itens($this->ativo) : collect();
    }

    public function escolher(string $assetId): void
    {
        $this->assetId = Asset::query()->whereKey($assetId)->value('id');
        $this->resultados = [];
        $this->observacoes = [];
        $this->resumo = null;
    }

    public function trocar(): void
    {
        $this->reset(['assetId', 'resultados', 'observacoes', 'resumo']);
    }

    public function marcar(string $requisitoId, string $resultado): void
    {
        if (in_array($resultado, ['ok', 'reprovado'], true)) {
            $this->resultados[$requisitoId] = $resultado;
        }
    }

    public function enviar(): void
    {
        Gate::authorize('viewAny', CatalogoItem::class);

        // So vale equipamento que o proprio usuario enxerga (a consulta ja respeita empresa e tecnico).
        $ativo = $this->ativo;
        abort_unless($ativo, 404);

        $respostas = [];
        foreach ($this->resultados as $id => $resultado) {
            $respostas[$id] = ['resultado' => $resultado, 'observacao' => $this->observacoes[$id] ?? null];
        }

        try {
            $this->resumo = app(ChecklistAptidaoService::class)->registrar($ativo, $respostas, auth()->user(), 'campo');
        } catch (ValidationException $e) {
            $this->addError('respostas', collect($e->errors())->flatten()->first());
        }
    }

    public function rotuloStatus(string $status): string
    {
        return AptidaoImpacto::rotulo($status);
    }

    public function render()
    {
        return view('livewire.checklist-aptidao-mobile');
    }
}
