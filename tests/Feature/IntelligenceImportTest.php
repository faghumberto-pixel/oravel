<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceBenchmark;
use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\DiagnosticoCalculator;
use App\Domain\Intelligence\Services\EvolucaoService;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Services\IndicadoresCalculator;
use App\Domain\Intelligence\Support\Catalogo;
use App\Filament\Pages\IntelligenceDashboard;
use App\Filament\Resources\IntelligenceImportResource\Pages\ListIntelligenceImports;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class IntelligenceImportTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $name = 'Locadora'): Tenant
    {
        $plan = Plan::create(['name' => 'P '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    /** @param list<array<string, mixed>> $linhas título => valor */
    private function planilha(array $linhas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'intel').'.xlsx';
        $titulos = array_column(Catalogo::campos(), 'title');
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName(Catalogo::SHEET);
        $w->addRow(Row::fromValues(array_column(Catalogo::campos(), 'group')));
        $w->addRow(Row::fromValues($titulos));
        foreach ($linhas as $l) {
            $w->addRow(Row::fromValues(array_map(fn ($t) => $l[$t] ?? null, $titulos)));
        }
        $w->close();

        return $path;
    }

    private function base(array $extra = []): array
    {
        return array_merge([
            'Data de Referência' => '30/09/2026', 'Porte' => 'médio', 'Total de Equipamentos' => 100, 'Equipamentos Locados' => 80,
            'Equipamentos Indisponíveis' => 15, 'Valor da Frota (R$)' => 9000000, 'Receita do Mês (R$)' => '450.000,00',
            'Custo de Manutenção do Mês (R$)' => 45000, 'Demais Custos do Mês (R$)' => 200000, 'OS Corretivas no Mês' => 30, 'OS Preventivas no Mês' => 10,
        ], $extra);
    }

    public function test_calcula_indicadores_e_diagnostico(): void
    {
        $i = (new IndicadoresCalculator)->calcular(['frota_total' => 100, 'frota_locada' => 80, 'frota_indisponivel' => 15, 'receita_mensal' => 1000.0, 'custo_manutencao' => 100.0, 'os_corretivas' => 30, 'os_preventivas' => 10, 'tecnicos' => 5, 'valor_frota' => 12000.0]);
        $this->assertSame(85.0, $i['disponibilidade_frota']['valor']);
        $this->assertSame(80.0, $i['utilizacao_frota']['valor']);
        $this->assertSame(10.0, $i['custo_manutencao_sobre_receita']['valor']);
        $this->assertSame(90.0, $i['margem_operacional']['valor']); // custo parcial ainda calcula com o que veio
        $this->assertSame(10.0, $i['receita_por_equipamento']['valor']);
        $this->assertSame(25.0, $i['preventiva_pct']['valor']);
        $this->assertSame(30.0, $i['corretivas_por_100_equipamentos']['valor']);
        $this->assertSame(20.0, $i['equipamentos_por_tecnico']['valor']);
        $this->assertSame(90.0, $i['retorno_sobre_frota']['valor']);
        $this->assertArrayNotHasKey('taxa_conversao_propostas', $i);

        $d = (new DiagnosticoCalculator)->calcular(['x' => ['valor' => 95.0, 'area' => 'frota', 'ruim' => 70.0, 'bom' => 95.0], 'y' => ['valor' => 25.0, 'area' => 'financeira', 'ruim' => 25.0, 'bom' => 5.0]]);
        $this->assertSame(100.0, $d['frota']['pontuacao']);
        $this->assertSame(0.0, $d['financeira']['pontuacao']);
        $this->assertSame(50.0, $d['geral']['pontuacao']);
        $this->assertSame([], (new DiagnosticoCalculator)->calcular([]));
    }

    public function test_importa_grava_snapshot_indicadores_e_diagnostico(): void
    {
        $t = $this->tenant();
        $r = app(ImportadorExcel::class)->importar($this->planilha([$this->base()]), $t, null, false, 'a.xlsx');

        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $s = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame('2026-09-30', $s->data_referencia->toDateString());
        $this->assertSame('Médio', $s->dados['porte']);
        $this->assertSame('concluida', $r['import']->fresh()->status);
        $this->assertSame(85.0, (float) $s->indicators()->where('chave', 'disponibilidade_frota')->value('valor'));
        $this->assertNotNull($s->diagnostics()->where('area', 'geral')->first());
    }

    public function test_simulacao_nao_grava_nada(): void
    {
        $t = $this->tenant();
        $r = app(ImportadorExcel::class)->importar($this->planilha([$this->base()]), $t, null, true);

        $this->assertTrue($r['ok']);
        $this->assertNotEmpty($r['indicadores']);
        $this->assertSame(0, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_planilha_identica_e_recusada_e_dado_novo_vira_snapshot_novo(): void
    {
        $t = $this->tenant();
        $imp = app(ImportadorExcel::class);
        $this->assertTrue($imp->importar($this->planilha([$this->base()]), $t)['ok']);

        $repetida = $imp->importar($this->planilha([$this->base()]), $t);
        $this->assertFalse($repetida['ok']);
        $this->assertStringContainsString('idêntica', $repetida['erros'][0]);

        $this->assertTrue($imp->importar($this->planilha([$this->base(['Data de Referência' => '31/10/2026'])]), $t)['ok']);
        $this->assertSame(2, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_validacoes(): void
    {
        $t = $this->tenant();
        $imp = app(ImportadorExcel::class);

        $r = $imp->importar($this->planilha([$this->base(['Equipamentos Locados' => 120])]), $t);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('não pode passar do total', $r['erros'][0]);

        $r = $imp->importar($this->planilha([$this->base(['Entregas no Prazo (%)' => 150, 'Porte' => 'Gigante'])]), $t);
        $this->assertCount(2, $r['erros']);

        $r = $imp->importar($this->planilha([$this->base(['Data de Referência' => '01/01/2999'])]), $t);
        $this->assertStringContainsString('futura', $r['erros'][0]);

        $r = $imp->importar($this->planilha([$this->base(), $this->base(['Data de Referência' => '31/10/2026'])]), $t);
        $this->assertStringContainsString('única fotografia', $r['erros'][0]);

        $r = $imp->importar($this->planilha([['Data de Referência' => '30/09/2026']]), $t);
        $this->assertStringContainsString('nenhum indicador', $r['erros'][0]);

        $this->assertSame(0, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_snapshot_e_imutavel(): void
    {
        $t = $this->tenant();
        $s = app(ImportadorExcel::class)->importar($this->planilha([$this->base()]), $t)['snapshot'];

        $this->expectException(\LogicException::class);
        $s->update(['hash' => 'outro']);
    }

    public function test_snapshot_nao_pode_ser_apagado(): void
    {
        $t = $this->tenant();
        $s = app(ImportadorExcel::class)->importar($this->planilha([$this->base()]), $t)['snapshot'];

        $this->expectException(\LogicException::class);
        $s->delete();
    }

    public function test_benchmark_recusa_amostra_pequena(): void
    {
        $this->expectException(\DomainException::class);
        IntelligenceBenchmark::create(['indicador' => 'utilizacao_frota', 'periodo' => '2026-09-01', 'amostra' => 4]);
    }

    public function test_evolucao_compara_snapshots_e_aponta_o_que_melhorou_e_piorou(): void
    {
        $t = $this->tenant();
        $imp = app(ImportadorExcel::class);
        $this->assertNull(app(EvolucaoService::class)->resumo($t->id));

        $imp->importar($this->planilha([$this->base(['Equipamentos Indisponíveis' => 20])]), $t);
        $imp->importar($this->planilha([$this->base(['Data de Referência' => '31/10/2026', 'Equipamentos Indisponíveis' => 10])]), $t);

        $r = app(EvolucaoService::class)->resumo($t->id);
        $disp = collect($r['indicadores'])->firstWhere('chave', 'disponibilidade_frota');
        $this->assertSame(90.0, $disp['valor']);
        $this->assertSame(80.0, $disp['anterior']);
        $this->assertSame('melhor', $disp['sentido']);
        $this->assertSame([80.0, 90.0], $disp['serie']);
        $this->assertSame('2026-09-30', $r['anterior']);
        $this->assertSame(2, $r['total_snapshots']);
        $this->assertGreaterThan(0, $r['atual']['geral']['delta']);
        $this->assertStringContainsString('Disponibilidade da Frota melhorou', implode(' ', $r['destaques']));
        $this->assertCount(2, $r['historico']);
    }

    public function test_tela_importa_e_dashboard_mostra_so_dados_do_proprio_tenant(): void
    {
        $t = $this->tenant('Minha');
        $outro = $this->tenant('Outra');
        app(ImportadorExcel::class)->importar($this->planilha([$this->base(['Total de Equipamentos' => 777, 'Equipamentos Locados' => 1, 'Equipamentos Indisponíveis' => 1])]), $outro);

        $chaves = [IntelligenceSnapshot::saasFeatureKey(), IntelligenceImport::saasFeatureKey(), 'menu_intelligence_dashboard'];
        $t->plan->update(['features' => $chaves]);
        $u = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(IntelligenceDashboard::class)->assertSee('Nenhuma fotografia da empresa ainda');

        $file = UploadedFile::fake()->createWithContent('i.xlsx', file_get_contents($this->planilha([$this->base()])));
        Livewire::test(ListIntelligenceImports::class)
            ->callAction('importar', ['arquivo' => $file, 'simular' => false])
            ->assertHasNoActionErrors();
        $this->assertSame($t->id, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->value('tenant_id'));

        Livewire::test(IntelligenceDashboard::class)
            ->assertSee('Índice geral da empresa')->assertSee('Disponibilidade da Frota')->assertDontSee('777');
    }
}
