<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\FrotaTrocaOleo;
use App\Services\Frota\EstoqueFrotaService;
use App\Services\Frota\OleoService;
use App\Services\Frota\OleoStatus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Registro de óleo (troca ou reposição) pelo celular, aberto pelo Dossiê do veículo (QR Code). */
#[Layout('layouts.checklist-mobile')]
class OleoFrotaMobile extends Component
{
    public Asset $ativo;

    public string $tipo = FrotaTrocaOleo::TROCA;

    public string $litros = '';

    public string $produto = '';

    public string $odometro = '';

    public ?string $pecaId = null;

    public ?string $almoxarifadoId = null;

    public ?string $mensagem = null;

    public ?string $erro = null;

    public function mount(string $assetId): void
    {
        $this->ativo = Asset::findOrFail($assetId);
        Gate::authorize('view', $this->ativo);
        Gate::authorize('create', FrotaTrocaOleo::class);
        abort_unless($this->ativo->isVehicle(), 404, 'Este ativo não é um veículo.');

        $this->odometro = (string) (int) floor((float) $this->ativo->odometro_atual);
        $this->produto = (string) $this->ativo->planoOleoAtivo()?->especificacao_oleo;
        $this->almoxarifadoId = ($id = EstoqueFrotaService::almoxarifadoDoUsuario()) ? (string) $id : null;
    }

    public function salvar(): void
    {
        $this->mensagem = $this->erro = null;

        try {
            $r = app(OleoService::class)->registrar($this->ativo, [
                'tipo' => $this->tipo, 'litros' => $this->litros, 'produto' => $this->produto, 'odometro' => (int) $this->odometro,
                'peca_id' => $this->pecaId ?: null, 'almoxarifado_id' => $this->almoxarifadoId ?: null,
            ], auth()->user());
            $this->mensagem = $r->tipo === FrotaTrocaOleo::TROCA ? 'Troca registrada.' : 'Reposição registrada.';
            $this->litros = '';
            $this->ativo->refresh();
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        return view('livewire.oleo-frota-mobile', [
            'status' => OleoStatus::para($this->ativo),
            'consumo' => OleoStatus::consumoAnormal($this->ativo),
            'pecas' => EstoqueFrotaService::opcoesPecas(),
            'almoxarifados' => EstoqueFrotaService::opcoesAlmoxarifados(),
        ]);
    }
}
