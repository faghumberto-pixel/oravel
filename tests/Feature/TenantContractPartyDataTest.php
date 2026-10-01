<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\CnpjLookupService;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Dados cadastrais do cliente (Contratante) no Contrato de Assinatura. */
class TenantContractPartyDataTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(array $extra = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Parte '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        return Tenant::create(array_merge([
            'name' => 'Topmixx', 'slug' => 'topmixx-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial',
        ], $extra));
    }

    public function test_cnpj_lookup_maps_registry_data(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response([
            'razao_social' => 'Topmixx Auto Pecas e Acessorios LTDA',
            'nome_fantasia' => 'Topmixx Auto Pecas e Acessorios',
            'natureza_juridica' => 'Sociedade Empresária Limitada',
            'descricao_tipo_de_logradouro' => 'RUA', 'logradouro' => 'DAS FLORES', 'numero' => '100',
            'bairro' => 'CENTRO', 'municipio' => 'CAMPINAS', 'uf' => 'SP', 'cep' => '13010000',
            'ddd_telefone_1' => '1933334444', 'email' => 'contato@topmixx.com.br',
        ])]);

        $data = app(CnpjLookupService::class)->lookup('12.925.998/0001-14');

        $this->assertSame('Topmixx Auto Pecas e Acessorios LTDA', $data['razao_social']);
        $this->assertSame('RUA DAS FLORES', $data['logradouro']);
        $this->assertSame('13010-000', $data['cep']);
        $this->assertSame('1933334444', $data['telefone']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cnpj/v1/12925998000114'));
    }

    public function test_cnpj_lookup_returns_null_for_invalid_or_unknown_cnpj(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response(['message' => 'não encontrado'], 404)]);

        $this->assertNull(app(CnpjLookupService::class)->lookup('123'));
        $this->assertNull(app(CnpjLookupService::class)->lookup('12925998000114'));
    }

    public function test_contract_shows_the_clients_registry_data(): void
    {
        $tenant = $this->makeTenant([
            'razao_social' => 'Topmixx Auto Pecas e Acessorios LTDA', 'nome_fantasia' => 'Topmixx Auto Pecas',
            'cpf_cnpj' => '12.925.998/0001-14', 'natureza_juridica' => 'Sociedade Empresária Limitada',
            'logradouro' => 'Rua das Flores', 'numero' => '100', 'cidade' => 'Campinas', 'uf' => 'SP', 'cep' => '13010-000',
            'representante_nome' => 'Fulano de Tal', 'representante_cargo' => 'Sócio-administrador',
        ]);

        $details = $tenant->contractPartyDetails();
        $this->assertSame('Topmixx Auto Pecas e Acessorios LTDA', $details['Razão social']);
        $this->assertSame('Fulano de Tal, Sócio-administrador', $details['Representante legal']);
        $this->assertArrayNotHasKey('Inscrição estadual', $details);

        $link = app(SignatureService::class)->generateSignatureLink($tenant, ['name' => 'Fulano', 'email' => 'f@topmixx.com.br']);
        $this->get($link)->assertOk()
            ->assertSee('Topmixx Auto Pecas e Acessorios LTDA')
            ->assertSee('12.925.998/0001-14')
            ->assertSee('Sociedade Empresária Limitada')
            ->assertSee('Rua das Flores, 100');
    }
}
