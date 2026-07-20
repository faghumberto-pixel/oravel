<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\User;
use Database\Seeders\SalesLeadIndustrialProspectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeadIndustrialProspectsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_prospects_and_updates_existing_ones_without_duplicating(): void
    {
        User::create([
            'name' => 'Humberto', 'email' => 'humberto@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);

        // Simula os 2 que ja existiam antes do seeder rodar em PROD --
        // segment/pipeline_stage propositalmente diferentes do "default"
        // pra provar que o seeder nao pisa nesses campos numa atualizacao.
        SalesLead::create([
            'company_name' => 'Geracamp',
            'segment' => 'eventos',
            'pipeline_stage' => SalesLead::STAGE_CONTATO_QUALIFICADO,
            'source' => SalesLead::SOURCE_INDICACAO,
        ]);
        SalesLead::create([
            'company_name' => 'Geradores Campinas',
            'segment' => 'construcao_civil',
            'pipeline_stage' => SalesLead::STAGE_DEMONSTRACAO_REALIZADA,
            'source' => SalesLead::SOURCE_SITE,
        ]);

        (new SalesLeadIndustrialProspectsSeeder)->run();

        $this->assertSame(5, SalesLead::count(), 'Deveria ter exatamente 5 leads (3 novos + 2 já existentes atualizados), sem duplicar.');

        $geracamp = SalesLead::where('company_name', 'Geracamp')->sole();
        $this->assertSame('eventos', $geracamp->segment, 'Segmento existente não pode ser sobrescrito.');
        $this->assertSame(SalesLead::STAGE_CONTATO_QUALIFICADO, $geracamp->pipeline_stage, 'Estágio existente não pode ser sobrescrito.');
        $this->assertSame(SalesLead::SOURCE_INDICACAO, $geracamp->source, 'Origem existente não pode ser sobrescrita.');
        $this->assertStringContainsString('Papelada física', $geracamp->critical_pain);
        $this->assertStringContainsString('Prancheta Zero', $geracamp->oravel_solution);

        $geradoresCampinas = SalesLead::where('company_name', 'Geradores Campinas')->sole();
        $this->assertSame('construcao_civil', $geradoresCampinas->segment);
        $this->assertStringContainsString('Ausência de ERP', $geradoresCampinas->critical_pain);

        $campGeradores = SalesLead::where('company_name', 'CampGeradores')->sole();
        $this->assertSame('Campinas', $campGeradores->city);
        $this->assertSame('industrial_hospitalar', $campGeradores->segment);
        $this->assertSame(SalesLead::SOURCE_PROSPECCAO_ATIVA, $campGeradores->source);
        $this->assertSame(SalesLead::STAGE_PROSPECCAO, $campGeradores->pipeline_stage);
        $this->assertNotNull($campGeradores->assigned_user_id);

        $superinfra = SalesLead::where('company_name', 'Superinfra')->sole();
        $this->assertSame('superinfra.com.br', $superinfra->website);

        $cfLocacoes = SalesLead::where('company_name', 'CF Locações')->sole();
        $this->assertSame('cflocacoes.com.br', $cfLocacoes->website);
        $this->assertSame('construcao_civil', $cfLocacoes->segment);

        // Roda de novo (idempotencia) -- nao pode duplicar nada.
        (new SalesLeadIndustrialProspectsSeeder)->run();
        $this->assertSame(5, SalesLead::count());
    }
}
