<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\FrotaPlanoOleoResource;
use App\Models\Asset;
use App\Models\FrotaPlanoOleo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/** Planos de oleo (Gestao de Frota): so edicao em modal; o plano nasce pelo OleoService. */
class FrotaPlanoOleoTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return FrotaPlanoOleo::class;
    }

    protected function resource(): string
    {
        return FrotaPlanoOleoResource::class;
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return $this->criar(Asset::class, [
            'tenant_id' => $tenant->id, 'name' => 'Caminhao '.uniqid(), 'status' => 'available',
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => strtoupper(substr(uniqid(), -7)),
        ]);
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(FrotaPlanoOleo::class, [
            'tenant_id' => $tenant->id, 'ativo_id' => $this->veiculo($tenant)->id,
            'intervalo_km' => 10000, 'intervalo_dias' => 180, 'especificacao_oleo' => '15W40', 'ativo' => true,
        ]);
    }

    public function test_editar_cria_novo_plano_ativo_e_desativa_o_anterior(): void
    {
        $plano = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('edit', $plano, data: [
                'ativo_id' => $plano->ativo_id, 'intervalo_km' => 12000, 'intervalo_dias' => 200, 'especificacao_oleo' => '10W40',
            ]);

        $planos = FrotaPlanoOleo::withoutGlobalScopes()->where('ativo_id', $plano->ativo_id)->get();
        $this->assertCount(2, $planos, 'o historico do plano anterior e preservado');
        $this->assertFalse((bool) $plano->fresh()->ativo);
        $novo = $planos->firstWhere('ativo', true);
        $this->assertSame(12000, $novo->intervalo_km);
        $this->assertSame('10W40', $novo->especificacao_oleo);
        $this->assertSame((string) $this->tenantA->id, (string) $novo->tenant_id);
    }

    public function test_exige_intervalo_em_km_ou_em_dias(): void
    {
        $plano = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('edit', $plano, data: ['ativo_id' => $plano->ativo_id, 'intervalo_km' => null, 'intervalo_dias' => null]);

        $this->assertTrue((bool) $plano->fresh()->ativo, 'plano antigo continua valendo');
        $this->assertSame(1, FrotaPlanoOleo::withoutGlobalScopes()->where('ativo_id', $plano->ativo_id)->count());
    }

    public function test_listagem_esconde_planos_desativados(): void
    {
        $plano = $this->novoRegistro($this->tenantA);
        $plano->forceFill(['ativo' => false])->save();
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))->assertCanNotSeeTableRecords([$plano]);
    }

    public function test_quem_so_le_nao_ve_a_acao_de_editar(): void
    {
        $plano = $this->novoRegistro($this->tenantA);
        $this->conceder($this->a['tecnico'], $this->slug(), ['ler']);
        $this->actingAs($this->a['tecnico']);

        Livewire::test($this->paginaDoResource('index'))->assertTableActionHidden('edit', $plano);
    }
}
