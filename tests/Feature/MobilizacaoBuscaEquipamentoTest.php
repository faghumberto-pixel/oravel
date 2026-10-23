<?php

namespace Tests\Feature;

use App\Livewire\AssetDemobilizationWizard;
use App\Livewire\AssetMobilizationWizard;
use App\Models\Asset;
use Livewire\Livewire;
use Tests\Feature\Modulos\Detalhado\PerfisTestCase;

/**
 * Busca manual do equipamento na Mobilizacao e na Desmobilizacao. Antes
 * (achado em 2026-10-23) nada chamava searchAsset() e a comparacao usava
 * LOWER(coluna) com o texto digitado sem converter: patrimonio em maiusculas
 * (o normal) nunca casava. Rode com DB_DATABASE descartavel.
 */
class MobilizacaoBuscaEquipamentoTest extends PerfisTestCase
{
    private function ativo($tenant, string $nome, string $pat, string $status = 'available'): Asset
    {
        return $this->criar(Asset::class, ['tenant_id' => $tenant->id, 'name' => $nome, 'patrimonio' => $pat, 'status' => $status]);
    }

    private function ids(array $resultados): array
    {
        return array_column($resultados, 'id');
    }

    public function test_digitar_em_maiusculas_lista_o_equipamento(): void
    {
        $gerador = $this->ativo($this->tenantA, 'Gerador 180 kVA', 'DEMO-M01');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)->set('assetSearch', 'DEMO-M01');

        $this->assertContains($gerador->id, $this->ids($lw->get('assetResults')));
    }

    public function test_busca_por_nome_ignora_maiusculas_e_minusculas(): void
    {
        $gerador = $this->ativo($this->tenantA, 'Gerador 180 kVA', 'PAT-1');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)->set('assetSearch', 'gErAdOr');

        $this->assertContains($gerador->id, $this->ids($lw->get('assetResults')));
    }

    public function test_menos_de_duas_letras_nao_lista_nada(): void
    {
        $this->ativo($this->tenantA, 'Gerador', 'PAT-1');
        $this->actingAs($this->a['admin']);

        Livewire::test(AssetMobilizationWizard::class)->set('assetSearch', 'g')->assertSet('assetResults', []);
    }

    public function test_nao_lista_equipamento_de_outro_tenant(): void
    {
        $deB = $this->ativo($this->tenantB, 'Gerador do B', 'PAT-B');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)->set('assetSearch', 'PAT-B');

        $this->assertNotContains($deB->id, $this->ids($lw->get('assetResults')));
    }

    public function test_tocar_no_resultado_seleciona_o_equipamento_e_limpa_a_lista(): void
    {
        $gerador = $this->ativo($this->tenantA, 'Gerador 180 kVA', 'DEMO-M01');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)
            ->set('assetSearch', 'DEMO')
            ->call('selectAsset', $gerador->id);

        $lw->assertSet('assetResults', [])->assertSet('assetSearch', null);
        $this->assertTrue($lw->get('asset')->is($gerador));
    }

    public function test_nao_seleciona_equipamento_de_outro_tenant_nem_fora_da_busca(): void
    {
        $deB = $this->ativo($this->tenantB, 'Gerador do B', 'PAT-B');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)
            ->set('assetSearch', 'PAT-B')
            ->call('selectAsset', $deB->id);

        $this->assertNull($lw->get('asset'));
    }

    public function test_desmobilizacao_so_oferece_equipamento_em_locacao(): void
    {
        $locado = $this->ativo($this->tenantA, 'Empilhadeira locada', 'EMP-1', 'em_locacao');
        $livre = $this->ativo($this->tenantA, 'Empilhadeira livre', 'EMP-2', 'available');
        $this->actingAs($this->a['admin']);

        $ids = $this->ids(Livewire::test(AssetDemobilizationWizard::class)->set('assetSearch', 'EMP')->get('assetResults'));

        $this->assertContains($locado->id, $ids);
        $this->assertNotContains($livre->id, $ids);
    }

    public function test_desmobilizacao_seleciona_o_equipamento_locado(): void
    {
        $locado = $this->ativo($this->tenantA, 'Empilhadeira locada', 'EMP-1', 'em_locacao');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetDemobilizationWizard::class)->set('assetSearch', 'emp')->call('selectAsset', $locado->id);

        $this->assertTrue($lw->get('asset')->is($locado));
    }

    // ---------------------------------------------------------------- QR da etiqueta

    public function test_qr_com_o_patrimonio_seleciona_o_equipamento(): void
    {
        $gerador = $this->ativo($this->tenantA, 'Gerador 180 kVA', 'DEMO-M01');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)->call('buscarPorCodigo', 'demo-m01');

        $this->assertTrue($lw->get('asset')->is($gerador));
        $lw->assertSet('qrMensagem', null);
    }

    public function test_qr_com_o_link_do_dossie_seleciona_pelo_id(): void
    {
        $gerador = $this->ativo($this->tenantA, 'Gerador 180 kVA', 'DEMO-M01');
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)
            ->call('buscarPorCodigo', 'https://app.oravel.com.br/admin/assets/dossie-mobile/'.$gerador->id);

        $this->assertTrue($lw->get('asset')->is($gerador));
    }

    public function test_qr_com_a_tag_ou_a_placa_tambem_funciona(): void
    {
        $veiculo = $this->criar(Asset::class, ['tenant_id' => $this->tenantA->id, 'name' => 'Caminhao', 'patrimonio' => 'V-1', 'status' => 'available', 'placa' => 'ABC1D23', 'tag' => 'TAG-77']);
        $this->actingAs($this->a['admin']);

        foreach (['ABC1D23', 'tag-77'] as $codigo) {
            $lw = Livewire::test(AssetMobilizationWizard::class)->call('buscarPorCodigo', $codigo);
            $this->assertTrue($lw->get('asset')->is($veiculo), $codigo);
        }
    }

    public function test_qr_desconhecido_mostra_mensagem_e_nao_seleciona(): void
    {
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)->call('buscarPorCodigo', 'NAO-EXISTE');

        $this->assertNull($lw->get('asset'));
        $lw->assertSet('qrMensagem', 'Nenhum equipamento encontrado para esse QR.');
    }

    public function test_qr_de_equipamento_de_outro_tenant_nao_seleciona(): void
    {
        $deB = $this->ativo($this->tenantB, 'Gerador do B', 'PAT-B');
        $this->actingAs($this->a['admin']);

        foreach (['PAT-B', 'https://x/admin/assets/dossie-mobile/'.$deB->id] as $codigo) {
            $this->assertNull(Livewire::test(AssetMobilizationWizard::class)->call('buscarPorCodigo', $codigo)->get('asset'), $codigo);
        }
    }

    public function test_link_com_id_que_nao_e_uuid_nao_quebra(): void
    {
        $this->actingAs($this->a['admin']);

        $lw = Livewire::test(AssetMobilizationWizard::class)->call('buscarPorCodigo', 'https://x/dossie-mobile/deadbeef-zzzz');

        $this->assertNull($lw->get('asset'));
        $lw->assertSet('qrMensagem', 'Nenhum equipamento encontrado para esse QR.');
    }

    public function test_desmobilizacao_so_aceita_qr_de_equipamento_em_locacao(): void
    {
        $locado = $this->ativo($this->tenantA, 'Empilhadeira locada', 'EMP-1', 'em_locacao');
        $livre = $this->ativo($this->tenantA, 'Empilhadeira livre', 'EMP-2', 'available');
        $this->actingAs($this->a['admin']);

        $this->assertTrue(Livewire::test(AssetDemobilizationWizard::class)->call('buscarPorCodigo', 'EMP-1')->get('asset')->is($locado));

        $semLocacao = Livewire::test(AssetDemobilizationWizard::class)->call('buscarPorCodigo', 'EMP-2');
        $this->assertNull($semLocacao->get('asset'));
        $semLocacao->assertSet('qrMensagem', 'Nenhum equipamento em locação encontrado para esse QR.');
        $this->assertNotNull($livre);
    }
}
