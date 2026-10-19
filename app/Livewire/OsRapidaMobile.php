<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "OS rápida" do campo: o técnico escolhe o equipamento da lista, diz o que está acontecendo (ou o que foi feito) e já cai no
 * modo campo da O.S. (MaintenanceOrderFieldWizard), onde ficam cronômetro, fotos e assinatura. Não duplica nada: só abre a O.S.
 * pelo mesmo modelo (número, tipo de serviço e parada do equipamento nascem nos eventos do próprio MaintenanceOrder).
 */
#[Layout('layouts.checklist-mobile')]
class OsRapidaMobile extends Component
{
    public string $busca = '';

    public ?string $assetId = null;

    public ?string $clientId = null;

    public string $local = '';

    public string $tipo = MaintenanceOrder::TYPE_CORRECTIVE;

    public string $descricao = '';

    public function mount(): void
    {
        Gate::authorize('create', MaintenanceOrder::class);
    }

    public function getEquipamentosProperty()
    {
        return Asset::query()
            ->when(trim($this->busca) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', '%'.trim($this->busca).'%')
                ->orWhere('tag', 'ilike', '%'.trim($this->busca).'%')
                ->orWhere('patrimonio', 'ilike', '%'.trim($this->busca).'%')))
            ->orderBy('name')->limit(40)->get(['id', 'name', 'tag', 'patrimonio']);
    }

    public function getClientesProperty()
    {
        return Client::query()->orderBy('name')->limit(200)->pluck('name', 'id');
    }

    public function escolher(string $assetId): void
    {
        $this->assetId = Asset::query()->whereKey($assetId)->value('id');
    }

    public function criar()
    {
        Gate::authorize('create', MaintenanceOrder::class);

        $dados = $this->validate([
            'assetId' => ['required', 'string'],
            'clientId' => ['nullable', 'string'],
            'local' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:'.MaintenanceOrder::TYPE_CORRECTIVE.','.MaintenanceOrder::TYPE_PREVENTIVE],
            'descricao' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'assetId.required' => 'Escolha o equipamento na lista.',
            'descricao.required' => 'Conte em poucas palavras o que está acontecendo ou o que foi feito.',
        ]);

        // Só vale equipamento/cliente que o próprio usuário enxerga (as consultas já respeitam empresa e técnico).
        $asset = Asset::query()->find($dados['assetId']);

        if (! $asset) {
            $this->addError('assetId', 'Esse equipamento não está disponível para você. Escolha outro da lista.');

            return null;
        }

        $cliente = $dados['clientId'] ? Client::query()->find($dados['clientId']) : null;

        $os = MaintenanceOrder::create([
            'tenant_id' => auth()->user()->tenant_id,
            'asset_id' => $asset->id,
            'client_id' => $cliente?->id,
            'technician_id' => auth()->id(),
            'maintenance_type' => $dados['tipo'],
            'status' => 'Aberto',
            'origin' => 'campo',
            'description' => trim(($dados['local'] ? 'Local: '.trim($dados['local'])."\n" : '').$dados['descricao']),
        ]);

        return $this->redirect(route('maintenance-orders.field-wizard', $os), navigate: false);
    }

    public function render()
    {
        return view('livewire.os-rapida-mobile');
    }
}
