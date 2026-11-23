<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ContractMeasurement;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\Catalogo;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IntelligenceExportService;
use App\Support\ExcelImport\XlsxReader;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligenceExportDoErpTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(array $features = []): Tenant
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'loc-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function ativo(Tenant $t, array $x = []): Asset
    {
        return Asset::factory()->create(array_merge(['tenant_id' => $t->id, 'patrimonio' => 'P'.uniqid(), 'acquisition_value' => 100000, 'manufacturing_year' => 2018, 'name' => 'Equip '.uniqid()], $x));
    }

    private function contrato(Tenant $t, Asset $a, string $ini, ?string $fim, string $cobranca, float $preco, string $status = 'Ativo'): Contract
    {
        return Contract::factory()->create(['tenant_id' => $t->id, 'asset_id' => $a->id, 'start_date' => $ini, 'end_date' => $fim, 'billing_type' => $cobranca, 'price' => $preco, 'status' => $status]);
    }

    private function os(Tenant $t, Asset $a, string $tipo, string $criada, ?string $concluida, float $custo, bool $retrabalho = false): MaintenanceOrder
    {
        return MaintenanceOrder::factory()->create(['tenant_id' => $t->id, 'asset_id' => $a->id, 'maintenance_type' => $tipo, 'created_at' => $criada, 'finished_at' => $concluida,
            'total_order_cost' => $custo, 'is_rework' => $retrabalho]);
    }

    private function exportar(Tenant $t, string $mes = '2026-09'): array
    {
        return app(IntelligenceExportService::class)->linhas($t->id, Carbon::createFromFormat('Y-m', $mes)->startOfMonth());
    }

    /** @return array<string, array<string, mixed>> por patrimônio */
    private function porPatrimonio(array $r): array
    {
        return collect($r['linhas'])->keyBy('Nº Patrimônio')->all();
    }

    public function test_calcula_custo_ordens_dias_e_receita_do_mes_com_as_regras_do_erp(): void
    {
        $t = $this->tenant();
        $a = $this->ativo($t, ['patrimonio' => 'emp-a ', 'manufacturing_year' => 2018]);
        $this->contrato($t, $a, '2026-08-01', '2026-09-15', 'mensal_fixo', 6000);
        $this->os($t, $a, 'Corretiva', '2026-09-05 08:00', '2026-09-08 17:00', 500, true);   // dentro dos dias locados: não conta como parado
        $this->os($t, $a, 'Corretiva', '2026-09-20 08:00', '2026-09-22 17:00', 300);          // 20, 21 e 22: parado
        $this->os($t, $a, 'Preventiva', '2026-09-25 08:00', '2026-09-25 18:00', 200);         // dia 25: parado
        $this->os($t, $a, 'Corretiva', '2026-07-01 08:00', '2026-07-10 17:00', 1000);         // só entra no acumulado
        $this->os($t, $a, 'Corretiva', '2026-10-05 08:00', '2026-10-06 17:00', 700);          // futuro: fora de tudo

        $r = $this->exportar($t);
        $l = $this->porPatrimonio($r)['EMP-A'];

        $this->assertSame('30/09/2026', $r['data_referencia']);
        $this->assertSame(1000.0, $l['Custo de Manutenção no Mês (R$)']);
        $this->assertSame(2000.0, $l['Custo de Manutenção Acumulado (R$)'], 'julho + setembro; outubro fica de fora');
        $this->assertSame([2, 1, 1], [$l['OS Corretivas no Mês'], $l['OS Preventivas no Mês'], $l['Corretivas Reincidentes no Mês']]);
        $this->assertSame(15, $l['Dias Locado no Mês']);
        $this->assertSame(4, $l['Dias Indisponível no Mês'], 'dias 20, 21, 22 e 25; os dias de OS em que estava locado não contam');
        $this->assertSame(3000.0, $l['Receita no Mês (R$)'], 'mensal fixo R$ 6.000 × 15 dias ÷ 30');
        $this->assertSame(100000.0, $l['Valor do Equipamento (R$)']);
        $this->assertSame(2018, $l['Ano de Fabricação']);
    }

    public function test_receita_por_tipo_de_cobranca_medicao_e_sem_estimativa(): void
    {
        $t = $this->tenant();
        $diaria = $this->ativo($t, ['patrimonio' => 'DIA']);
        $this->contrato($t, $diaria, '2026-09-10', '2026-09-19', 'diaria', 400);
        $porHora = $this->ativo($t, ['patrimonio' => 'HORA']);
        $this->contrato($t, $porHora, '2026-09-01', null, 'por_hora', 50);
        $medido = $this->ativo($t, ['patrimonio' => 'MED']);
        $c = $this->contrato($t, $medido, '2026-08-01', null, 'mensal_fixo', 1000);
        ContractMeasurement::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'contract_id' => $c->id, 'reference_period_start' => '2026-09-01', 'reference_period_end' => '2026-09-30',
            'total_days_in_period' => 30, 'prorated_days' => 30, 'total_base_amount' => 1234.56, 'total_excess_hours_amount' => 0, 'total_extras_amount' => 0, 'total_amount' => 1234.56, 'status' => 'approved']);
        $rascunho = $this->ativo($t, ['patrimonio' => 'RASC']);
        $this->contrato($t, $rascunho, '2026-09-01', null, 'mensal_fixo', 9999, 'Draft');
        $sem = $this->ativo($t, ['patrimonio' => 'SEM']);

        $l = $this->porPatrimonio($this->exportar($t));

        $this->assertSame(4000.0, $l['DIA']['Receita no Mês (R$)'], 'diária × 10 dias');
        $this->assertSame(10, $l['DIA']['Dias Locado no Mês']);
        $this->assertNull($l['HORA']['Receita no Mês (R$)'], 'por hora sem medição não é estimado');
        $this->assertSame(1234.56, $l['MED']['Receita no Mês (R$)'], 'a medição aprovada prevalece sobre a estimativa');
        $this->assertSame(0.0, $l['RASC']['Receita no Mês (R$)'], 'contrato em rascunho não gera receita');
        $this->assertSame(0, $l['RASC']['Dias Locado no Mês']);
        $this->assertSame([0.0, 0], [$l['SEM']['Receita no Mês (R$)'], $l['SEM']['Dias Locado no Mês']]);
        $this->assertContains('1 equipamento(s) têm contrato por hora ou por franquia sem medição aprovada no mês: a receita deles ficou em branco.', $this->exportar($t)['avisos']);
    }

    public function test_ignora_sem_patrimonio_repetidos_apagados_e_outras_empresas(): void
    {
        $t = $this->tenant();
        $outra = $this->tenant();
        $this->ativo($t, ['patrimonio' => 'A-1']);
        $this->ativo($t, ['patrimonio' => 'a-1 ']);                       // repetido (mesmo código depois de normalizar)
        $this->ativo($t, ['patrimonio' => null]);                          // sem patrimônio
        $apagado = $this->ativo($t, ['patrimonio' => 'APAGADO']);
        \DB::table('assets')->where('id', $apagado->id)->update(['deleted_at' => now()]);
        $this->ativo($outra, ['patrimonio' => 'DA-OUTRA']);
        $os = $this->ativo($outra, ['patrimonio' => 'A-1']);
        $this->os($outra, $os, 'Corretiva', '2026-09-05 08:00', '2026-09-06 08:00', 999);

        $r = $this->exportar($t);

        $this->assertSame(['A-1'], array_column($r['linhas'], 'Nº Patrimônio'));
        $this->assertSame(0.0, $r['linhas'][0]['Custo de Manutenção no Mês (R$)'], 'a OS da outra empresa não entra, nem com o mesmo patrimônio');
        $this->assertSame(2, $r['ignorados']);
        $this->assertNotEmpty(array_filter($r['avisos'], fn ($a) => str_contains($a, 'repetido')));
    }

    public function test_a_planilha_exportada_importa_sem_erro_no_intelligence(): void
    {
        $t = $this->tenant(['tabela_intelligence_equipamentos' => true]);
        $a = $this->ativo($t, ['patrimonio' => 'EMP-1', 'acquisition_value' => 100000]);
        $b = $this->ativo($t, ['patrimonio' => 'EMP-2', 'acquisition_value' => 50000, 'manufacturing_year' => 1900]);
        $this->contrato($t, $a, '2026-08-01', null, 'mensal_fixo', 6000);
        $this->os($t, $a, 'Corretiva', '2026-09-05 08:00', '2026-09-08 17:00', 500, true);
        $this->os($t, $b, 'Preventiva', '2026-09-12 08:00', '2026-09-12 18:00', 200);

        $res = $this->exportar($t);
        $arquivo = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        app(IntelligenceExportService::class)->escrever($res, $arquivo);

        $this->assertNotNull(XlsxReader::rowsDaAba($arquivo, Catalogo::SHEET));
        $this->assertCount(1 + 2, XlsxReader::rowsDaAba($arquivo, Catalogo::SHEET_EQUIPAMENTOS), 'cabeçalho + 2 equipamentos, sem linha de exemplo');

        $imp = app(ImportadorExcel::class)->importar($arquivo, $t);
        $this->assertTrue($imp['ok'], implode(' | ', $imp['erros']));
        $this->assertSame(2, $imp['equipamentos']);
        $s = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame('2026-09-30', $s->data_referencia->toDateString());
        $this->assertSame(2, $s->dados['frota_total']);
        $this->assertSame(1, $s->dados['os_preventivas']);
        $this->assertSame(1, $s->dados['equip_reincidencia_pct'] > 0 ? 1 : 0, 'a reincidência exportada chegou ao indicador da empresa');
        $this->assertNull(\DB::table('intelligence_equipamentos')->where('snapshot_id', $s->id)->where('patrimonio', 'EMP-2')->value('ano_fabricacao'), 'ano absurdo (1900) vira em branco, não erro');
    }

    public function test_botao_so_aparece_com_o_intelligence_e_baixa_a_planilha(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $com = $this->tenant([Asset::saasFeatureKey() => true, 'tabela_intelligence_equipamentos' => true]);
        $this->ativo($com, ['patrimonio' => 'EMP-9']);
        $admin = fn (Tenant $t) => tap(User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@t.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]), function ($u) use ($t) {
            $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        });

        $this->actingAs($admin($com));
        $c = Livewire::test(ListAssets::class)->assertActionVisible('exportarParaIntelligence');
        $c->callAction('exportarParaIntelligence', ['mes' => now()->subMonth()->format('Y-m')])->assertFileDownloaded('intelligence-equipamentos-'.now()->subMonth()->format('Y-m').'.xlsx');

        $arquivo = tempnam(sys_get_temp_dir(), 'dl').'.xlsx';
        file_put_contents($arquivo, base64_decode($c->effects['download']['content']));
        $linhas = XlsxReader::rowsDaAba($arquivo, Catalogo::SHEET_EQUIPAMENTOS);
        $this->assertSame('EMP-9', $linhas[2][0]);

        // sem o Intelligence no contrato, o botão não existe
        $sem = $this->tenant([Asset::saasFeatureKey() => true]);
        $this->flushSession();
        $this->actingAs($admin($sem));
        Livewire::test(ListAssets::class)->assertActionHidden('exportarParaIntelligence');
    }
}
