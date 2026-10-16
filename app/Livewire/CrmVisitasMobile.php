<?php

namespace App\Livewire;

use App\Models\CrmConta;
use App\Models\CrmVeiculo;
use App\Models\CrmVisita;
use App\Models\CrmVisitaDespesa;
use App\Services\CrmVisitaService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Minhas visitas": tela de campo do vendedor no celular. Hoje, check-in com localização do aparelho,
 * finalizar (resultado, km, próximo passo), despesa rápida com foto do comprovante e visita avulsa.
 */
#[Layout('layouts.checklist-mobile')]
class CrmVisitasMobile extends Component
{
    use WithFileUploads;

    public string $dia = '';

    public ?string $finalizandoId = null;

    public string $resultado = '';

    public ?string $kmFim = null;

    public ?string $proximoPasso = null;

    public ?string $proximoContato = null;

    public bool $naoEncontrou = false;

    public ?string $despesaVisitaId = null;

    public string $despesaTipo = 'combustivel';

    public ?string $despesaValor = null;

    public ?string $despesaDescricao = null;

    public $comprovante = null;

    public bool $mostrandoAvulsa = false;

    public ?string $avulsaContaId = null;

    public string $avulsaNome = '';

    /** @var list<string> */
    public array $avisos = [];

    public ?string $erro = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', CrmVisita::class);
        $this->dia = now()->toDateString();
    }

    public function getVisitasProperty()
    {
        return CrmVisita::query()
            ->where('user_id', auth()->id())
            ->doDia($this->dia)
            ->with(['lead', 'conta', 'veiculo'])
            ->orderByRaw('ordem_roteiro asc nulls last')->orderBy('agendada_para')
            ->get();
    }

    public function getContasProperty()
    {
        return CrmConta::query()->orderBy('nome')->limit(200)->pluck('nome', 'id');
    }

    public function getVeiculosProperty()
    {
        return CrmVeiculo::where('ativo', true)->orderBy('placa')->get();
    }

    public function checkin(string $id, ?float $lat = null, ?float $lng = null, ?string $veiculoId = null): void
    {
        $this->erro = null;
        $visita = $this->minhaVisita($id);

        $this->executar(function () use ($visita, $lat, $lng, $veiculoId) {
            $r = app(CrmVisitaService::class)->checkin($visita, auth()->user(), $lat, $lng, $veiculoId ?: null);
            $this->avisos = $r['avisos'];
        });
    }

    public function abrirFinalizar(string $id): void
    {
        $this->minhaVisita($id);
        $this->finalizandoId = $id;
        $this->reset(['resultado', 'kmFim', 'proximoPasso', 'proximoContato', 'naoEncontrou']);
    }

    public function finalizar(): void
    {
        $this->erro = null;
        $visita = $this->minhaVisita((string) $this->finalizandoId);

        $this->executar(function () use ($visita) {
            app(CrmVisitaService::class)->checkout($visita, auth()->user(), [
                'resultado' => $this->resultado,
                'km_fim' => $this->kmFim !== null && $this->kmFim !== '' ? (int) $this->kmFim : null,
                'proximo_passo' => $this->proximoPasso,
                'proximo_contato' => $this->proximoContato ?: null,
                'status' => $this->naoEncontrou ? CrmVisita::STATUS_NAO_ENCONTROU : CrmVisita::STATUS_REALIZADA,
            ]);
            $this->finalizandoId = null;
        });
    }

    public function cancelarFinalizar(): void
    {
        $this->finalizandoId = null;
    }

    public function abrirDespesa(?string $visitaId = null): void
    {
        $this->despesaVisitaId = $visitaId ?: '';
        $this->reset(['despesaValor', 'despesaDescricao', 'comprovante']);
        $this->despesaTipo = 'combustivel';
    }

    public function fecharDespesa(): void
    {
        $this->despesaVisitaId = null;
    }

    public function lancarDespesa(): void
    {
        Gate::authorize('create', CrmVisitaDespesa::class);

        $dados = $this->validate([
            'despesaTipo' => ['required', 'in:'.implode(',', array_keys(CrmVisitaDespesa::tipoLabels()))],
            'despesaValor' => ['required', 'numeric', 'min:0.01'],
            'despesaDescricao' => ['nullable', 'string', 'max:255'],
            'comprovante' => ['nullable', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $visita = $this->despesaVisitaId ? $this->minhaVisita($this->despesaVisitaId) : null;

        $despesa = CrmVisitaDespesa::create([
            'tenant_id' => Tenancy::current()?->id ?? auth()->user()->tenant_id,
            'crm_visita_id' => $visita?->id,
            'user_id' => auth()->id(),
            'crm_veiculo_id' => $visita?->crm_veiculo_id,
            'data' => now()->toDateString(),
            'tipo' => $dados['despesaTipo'],
            'valor' => $dados['despesaValor'],
            'descricao' => $dados['despesaDescricao'] ?? null,
        ]);

        if ($this->comprovante) {
            $despesa->addMedia($this->comprovante->getRealPath())->usingFileName($this->comprovante->getClientOriginalName())->toMediaCollection('comprovante');
        }

        $this->despesaVisitaId = null;
        $this->avisos = ['Despesa lançada (pendente de aprovação).'];
    }

    public function salvarAvulsa(): void
    {
        $this->erro = null;
        Gate::authorize('create', CrmVisita::class);

        if (blank($this->avulsaContaId) && trim($this->avulsaNome) === '') {
            $this->erro = 'Escolha uma conta ou informe quem está sendo visitado.';

            return;
        }

        CrmVisita::create([
            'tenant_id' => Tenancy::current()?->id ?? auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'crm_conta_id' => $this->avulsaContaId ?: null,
            'contato_nome' => trim($this->avulsaNome) ?: null,
            'objetivo' => 'apresentacao',
            'agendada_para' => now(),
            'status' => CrmVisita::STATUS_AGENDADA,
        ]);

        $this->reset(['mostrandoAvulsa', 'avulsaContaId', 'avulsaNome']);
        $this->dia = now()->toDateString();
    }

    private function minhaVisita(string $id): CrmVisita
    {
        // Vendedor só enxerga as próprias visitas (mesmo que passe o id de outra).
        return CrmVisita::query()->where('user_id', auth()->id())->findOrFail($id);
    }

    private function executar(\Closure $acao): void
    {
        try {
            $acao();
        } catch (\DomainException $e) {
            $this->erro = $e->getMessage();
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        return view('livewire.crm-visitas-mobile');
    }
}
