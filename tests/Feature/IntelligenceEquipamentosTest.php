<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceEquipamento;
use App\Domain\Intelligence\Models\IntelligenceEquipamentoAnalise;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\Catalogo;
use App\Domain\Intelligence\Support\ModeloExcel;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\ExcelImport\XlsxReader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class IntelligenceEquipamentosTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $nome = 'Locadora', bool $comChave = true): Tenant
    {
        $features = $comChave ? ['tabela_intelligence_equipamentos' => true] : [];
        $plano = Plan::create(['name' => 'P '.$nome.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => $nome, 'slug' => str($nome)->slug().'-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    /** @param list<array<string, mixed>>|null $equip título => valor; null = planilha SEM a aba Equipamentos */
    private function planilha(array $dados, ?array $equip): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ieq').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName(Catalogo::SHEET);
        $t = array_column(Catalogo::campos(), 'title');
        $w->addRow(Row::fromValues(array_column(Catalogo::campos(), 'group')));
        $w->addRow(Row::fromValues($t));
        $w->addRow(Row::fromValues(array_map(fn ($x) => $dados[$x] ?? null, $t)));
        if ($equip !== null) {
            $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::SHEET_EQUIPAMENTOS);
            $te = array_column(Catalogo::equipamentos(), 'title');
            $w->addRow(Row::fromValues($te));
            foreach ($equip as $l) {
                $w->addRow(Row::fromValues(array_map(fn ($x) => $l[$x] ?? null, $te)));
            }
        }
        $w->close();

        return $path;
    }

    private function eq(string $pat, array $extra = []): array
    {
        return array_merge(['Nº Patrimônio' => $pat, 'Nome / Modelo' => 'Empilhadeira '.$pat, 'Categoria' => 'Empilhadeira', 'Ano de Fabricação' => 2018, 'Valor do Equipamento (R$)' => 100000,
            'Receita no Mês (R$)' => 9000, 'Custo de Manutenção no Mês (R$)' => 1000, 'Custo de Manutenção Acumulado (R$)' => 10000, 'OS Corretivas no Mês' => 2, 'OS Preventivas no Mês' => 1,
            'Corretivas Reincidentes no Mês' => 0, 'Dias Locado no Mês' => 20, 'Dias Indisponível no Mês' => 2], $extra);
    }

    private function dados(string $data = '30/09/2026', array $extra = []): array
    {
        return array_merge(['Data de Referência' => $data, 'Porte' => 'Médio'], $extra);
    }

    private function frota(): array
    {
        return [
            $this->eq('EMP-1'),
            $this->eq('EMP-2', ['Custo de Manutenção Acumulado (R$)' => 67000, 'OS Corretivas no Mês' => 4, 'Corretivas Reincidentes no Mês' => 2]),
            $this->eq('EMP-3', ['Dias Locado no Mês' => 5, 'Dias Indisponível no Mês' => 1]),
            $this->eq('EMP-4', ['Valor do Equipamento (R$)' => 200000]),
        ];
    }

    public function test_importa_a_aba_e_gera_campos_analises_e_indicadores_da_empresa(): void
    {
        $t = $this->tenant();
        $r = app(ImportadorExcel::class)->importar($this->planilha($this->dados(), $this->frota()), $t);

        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $this->assertSame(4, $r['equipamentos']);
        $this->assertSame(['saudavel' => 2, 'trocar_vender' => 1, 'subutilizado' => 1], array_intersect_key($r['classificacoes'], array_flip(['saudavel', 'trocar_vender', 'subutilizado'])));

        $s = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame(4, $s->dados['frota_total'], 'gerado da soma da aba');
        $this->assertSame(500000.0, (float) $s->dados['valor_frota']);
        $this->assertSame(36000.0, (float) $s->dados['receita_mensal']);
        $this->assertContains('frota_total', $s->dados['derivados_dos_equipamentos']);
        $this->assertSame(4, IntelligenceEquipamento::withoutGlobalScopes()->where('snapshot_id', $s->id)->count());
        $this->assertSame(4, IntelligenceEquipamentoAnalise::withoutGlobalScopes()->where('snapshot_id', $s->id)->count());

        $emp2 = IntelligenceEquipamento::withoutGlobalScopes()->where('snapshot_id', $s->id)->where('patrimonio', 'EMP-2')->firstOrFail();
        $this->assertSame('trocar_vender', $emp2->analise->classificacao);
        $this->assertStringContainsString('67,0%', $emp2->analise->motivo);
        $this->assertSame($t->id, $emp2->tenant_id);

        // indicadores novos, vindos da classificação por equipamento
        $chaves = $s->indicators()->pluck('valor', 'chave');
        $this->assertSame(20.0, round((float) $chaves['reincidencia_pct'], 2), '2 reincidências em 10 corretivas (2+4+2+2)');
        $this->assertSame(25.0, (float) $chaves['subutilizados_pct']);
        $this->assertArrayHasKey('valor_trocar_vender_pct', $chaves->all());
    }

    public function test_o_que_o_cliente_digitou_prevalece_e_diverge_vira_aviso(): void
    {
        $t = $this->tenant();
        $r = app(ImportadorExcel::class)->importar($this->planilha($this->dados('30/09/2026', ['Total de Equipamentos' => 120, 'Receita do Mês (R$)' => 36000]), $this->frota()), $t);

        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $s = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame(120, (int) $s->dados['frota_total'], 'o digitado prevalece');
        $this->assertNotContains('frota_total', $s->dados['derivados_dos_equipamentos']);
        $this->assertNotContains('receita_mensal', $s->dados['derivados_dos_equipamentos'], 'a receita também foi digitada');
        $this->assertCount(0, array_filter($r['avisos'], fn ($x) => str_contains($x, 'Receita')), 'receita bate com a soma: sem aviso');
        $this->assertCount(1, array_filter($r['avisos'], fn ($a) => str_contains($a, 'Total de Equipamentos')));
        $this->assertStringContainsString('informou 120', $r['avisos'][0]);
        $this->assertStringContainsString('dá 4', $r['avisos'][0]);
    }

    public function test_sem_a_aba_tudo_funciona_como_antes(): void
    {
        $t = $this->tenant();
        $r = app(ImportadorExcel::class)->importar($this->planilha($this->dados('30/09/2026', ['Total de Equipamentos' => 100, 'Equipamentos Locados' => 80]), null), $t);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['equipamentos']);
        $this->assertSame([], $r['avisos']);
        $this->assertSame(0, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $s = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertArrayNotHasKey('derivados_dos_equipamentos', $s->dados);
        $this->assertArrayNotHasKey('equip_reincidencia_pct', $s->dados);
    }

    public function test_contrato_sem_a_chave_ignora_a_aba_e_avisa(): void
    {
        $t = $this->tenant('Sem chave', false);
        $r = app(ImportadorExcel::class)->importar($this->planilha($this->dados('30/09/2026', ['Total de Equipamentos' => 100, 'Equipamentos Locados' => 80]), $this->frota()), $t);

        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $this->assertSame(0, $r['equipamentos']);
        $this->assertStringContainsString('ignorada', $r['avisos'][0]);
        $this->assertSame(0, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame(100, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail()->dados['frota_total']);
    }

    public function test_validacoes_da_aba_apontam_a_linha_e_nao_gravam_nada(): void
    {
        $t = $this->tenant();
        $imp = app(ImportadorExcel::class);

        $r = $imp->importar($this->planilha($this->dados(), [$this->eq('A'), $this->eq('a')]), $t);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('linha 3', $r['erros'][0]);
        $this->assertStringContainsString('repetido', $r['erros'][0], 'A e a são o mesmo patrimônio');

        $r = $imp->importar($this->planilha($this->dados(), [$this->eq('B', ['Dias Locado no Mês' => 25, 'Dias Indisponível no Mês' => 10])]), $t);
        $this->assertStringContainsString('não podem passar de 30', $r['erros'][0]);

        $r = $imp->importar($this->planilha($this->dados(), [$this->eq('C', ['OS Corretivas no Mês' => 1, 'Corretivas Reincidentes no Mês' => 3])]), $t);
        $this->assertStringContainsString('não pode passar das OS Corretivas', $r['erros'][0]);

        $r = $imp->importar($this->planilha($this->dados(), [$this->eq('D', ['Ano de Fabricação' => 1800, 'Valor do Equipamento (R$)' => -5])]), $t);
        $this->assertStringContainsString('Ano de Fabricação', $r['erros'][0]);
        $this->assertStringContainsString('negativo', $r['erros'][0]);

        $r = $imp->importar($this->planilha($this->dados(), [$this->eq('', ['Nº Patrimônio' => null])]), $t);
        $this->assertStringContainsString('Nº Patrimônio: obrigatório', $r['erros'][0]);

        $this->assertSame(0, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame(0, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_simulacao_nao_grava_equipamentos(): void
    {
        $t = $this->tenant();
        $r = app(ImportadorExcel::class)->importar($this->planilha($this->dados(), $this->frota()), $t, null, true);

        $this->assertTrue($r['ok']);
        $this->assertSame(4, $r['equipamentos']);
        $this->assertSame(0, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame(0, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_o_mes_seguinte_usa_o_anterior_para_tendencia_e_risco(): void
    {
        $t = $this->tenant();
        $imp = app(ImportadorExcel::class);
        $this->assertTrue($imp->importar($this->planilha($this->dados('31/08/2026'), [$this->eq('EMP-1'), $this->eq('EMP-9')]), $t)['ok']);

        // setembro: EMP-1 piora (custo acumulado passa de 60%), EMP-9 some e EMP-7 é novo
        $r = $imp->importar($this->planilha($this->dados('30/09/2026'), [$this->eq('EMP-1', ['Custo de Manutenção Acumulado (R$)' => 70000]), $this->eq('EMP-7')]), $t);
        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));

        $set = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $t->id)->where('data_referencia', '2026-09-30')->firstOrFail();
        $a = fn ($pat) => IntelligenceEquipamentoAnalise::withoutGlobalScopes()->where('snapshot_id', $set->id)->where('patrimonio', $pat)->firstOrFail();
        $this->assertSame('piorando', $a('EMP-1')->tendencia);
        $this->assertSame('trocar_vender', $a('EMP-1')->classificacao);
        $this->assertNull($a('EMP-7')->tendencia, 'equipamento novo não tem tendência');
    }

    public function test_hash_inclui_os_equipamentos_e_a_analise_do_outro_cliente_nao_vaza(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $imp = app(ImportadorExcel::class);

        $dados = $this->dados('30/09/2026', ['Total de Equipamentos' => 100, 'Equipamentos Locados' => 80]);
        $this->assertTrue($imp->importar($this->planilha($dados, [$this->eq('X-1')]), $a)['ok']);
        $igual = $imp->importar($this->planilha($dados, [$this->eq('X-1')]), $a);
        $this->assertFalse($igual['ok']);
        $this->assertStringContainsString('idêntica', $igual['erros'][0]);
        $this->assertTrue($imp->importar($this->planilha($dados, [$this->eq('X-1', ['Receita no Mês (R$)' => 100])]), $a)['ok'], 'mesmos Dados, equipamento diferente = fotografia nova');

        // o cliente B tem o MESMO patrimônio e nunca enxerga o histórico de A
        $this->assertTrue($imp->importar($this->planilha($this->dados('31/10/2026'), [$this->eq('X-1')]), $b)['ok']);
        $sb = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $b->id)->firstOrFail();
        $this->assertNull(IntelligenceEquipamentoAnalise::withoutGlobalScopes()->where('snapshot_id', $sb->id)->firstOrFail()->tendencia);
        $this->assertSame(1, IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $b->id)->count());
    }

    public function test_equipamento_gravado_e_imutavel(): void
    {
        $t = $this->tenant();
        app(ImportadorExcel::class)->importar($this->planilha($this->dados(), $this->frota()), $t);
        $e = IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();

        $this->expectException(\LogicException::class);
        $e->update(['nome' => 'Outro']);
    }

    public function test_modelo_de_planilha_tem_as_tres_abas_e_as_colunas_do_catalogo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
        ModeloExcel::escrever($path);

        $dados = XlsxReader::rowsDaAba($path, Catalogo::SHEET);
        $equip = XlsxReader::rowsDaAba($path, Catalogo::SHEET_EQUIPAMENTOS);
        $this->assertNotNull($dados);
        $this->assertNotNull($equip);
        $this->assertNotNull(XlsxReader::rowsDaAba($path, 'Instruções'));
        $this->assertNull(XlsxReader::rowsDaAba($path, 'Não existe'), 'aba inexistente não cai na primeira');
        $this->assertCount(13, $equip[1]);
        $this->assertSame('Nº Patrimônio *', $equip[1][0]);
        $this->assertStringStartsWith('(EXEMPLO', (string) $equip[2][0]);

        // o modelo em branco (só com os exemplos) é aceito pelo leitor e recusado só por não ter dados
        $r = app(ImportadorExcel::class)->importar($path, $this->tenant());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('nenhuma linha de dados', $r['erros'][0]);
    }

    public function test_migration_liga_a_chave_so_nos_contratos_do_intelligence(): void
    {
        $mk = fn (string $nome, ?string $pacote, ?array $pacotes, array $f) => Plan::create(['name' => $nome, 'pacote' => $pacote, 'pacotes' => $pacotes, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $f]);
        $int = $mk('Intelligence', 'intelligence', null, ['tabela_intelligence_snapshots' => true]);
        $erp = $mk('Só ERP', 'locadora', null, ['tabela_clients' => true]);

        (require database_path('migrations/2027_01_14_100000_backfill_equipamentos_do_intelligence_nos_contratos.php'))->up();

        $this->assertTrue($int->fresh()->hasFeature('tabela_intelligence_equipamentos'));
        $this->assertTrue($int->fresh()->hasFeature('tabela_intelligence_snapshots'));
        $this->assertFalse($erp->fresh()->hasFeature('tabela_intelligence_equipamentos'));
    }
}
