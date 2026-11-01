<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ProspectResource;
use App\Filament\Central\Resources\ProspectResource\Pages\ListProspects;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProspectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function prospect(array $o = []): Prospect
    {
        return Prospect::create($o + ['cnpj' => '11.111.111/0001-'.random_int(10, 99), 'nome' => 'Obra Mais', 'socio' => 'FRANCO DE ANDRADE', 'ano_abertura' => 2025, 'telefone' => '(19) 98888-7777', 'nota' => 9]);
    }

    public function test_mensagem_usa_primeiro_nome_empresa_e_ano(): void
    {
        $m = $this->prospect()->mensagemAbordagem();
        $this->assertStringContainsString('Olá, Franco.', $m);
        $this->assertStringContainsString('a Obra Mais abriu em 2025', $m);
    }

    public function test_celular_x_fixo(): void
    {
        $this->assertTrue($this->prospect()->ehCelular());
        $this->assertFalse($this->prospect(['telefone' => '(19) 3833-2432'])->ehCelular());
    }

    public function test_super_admin_lista_e_marca_enviei(): void
    {
        $p = $this->prospect();
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        Livewire::actingAs($u)->test(ListProspects::class)->assertCanSeeTableRecords([$p])
            ->callTableAction('enviei', $p);
        $p->refresh();
        $this->assertSame('enviado', $p->status);
        $this->assertNotNull($p->proximo_contato);
        $this->assertCount(1, $p->contatos);
    }

    public function test_nao_super_admin_nao_acessa(): void
    {
        $u = User::factory()->create(['email' => Str::random(8).'@x.test']);
        $this->actingAs($u);
        $this->assertFalse(ProspectResource::canViewAny());
    }

    public function test_modal_da_mensagem_mostra_as_objecoes(): void
    {
        $p = $this->prospect();
        $html = view('filament.central.prospect-mensagem', ['abordagem' => $p->mensagemAbordagem(), 'followup' => $p->mensagemFollowUp(), 'objecoes' => Prospect::OBJECOES])->render();
        $this->assertStringContainsString('Se o sócio responder', $html);
        $this->assertStringContainsString('R$ 280 por mês', $html);
    }

    public function test_filtro_de_ano_e_card_aplicam_o_mesmo_recorte(): void
    {
        $velha = $this->prospect(['ano_abertura' => 2010, 'nome' => 'Velha']);
        $nova = $this->prospect(['ano_abertura' => 2025, 'nome' => 'Nova', 'empresa_nova' => true]);
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        Livewire::actingAs($u)->test(ListProspects::class)
            ->filterTable('ano_abertura', ['de' => 2024, 'ate' => null])
            ->assertCanSeeTableRecords([$nova])->assertCanNotSeeTableRecords([$velha])
            ->call('filtrarCard', 'nova')
            ->assertCanSeeTableRecords([$nova])->assertCanNotSeeTableRecords([$velha])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$nova, $velha]);
    }

    public function test_impressao_respeita_filtros_e_so_super_admin(): void
    {
        $this->prospect(['nome' => 'Velha Locadora', 'ano_abertura' => 2010]);
        $this->prospect(['nome' => 'Nova Locadora', 'ano_abertura' => 2025]);
        $query = ['filtros' => ['ano_abertura' => ['de' => 2024]]];

        $this->actingAs(User::factory()->create(['email' => Str::random(8).'@x.test']))
            ->get(route('central.prospects.imprimir', $query))->assertForbidden();

        $r = $this->actingAs(User::factory()->create(['email' => 'equipe@oravel.test']))->get(route('central.prospects.imprimir', $query));
        $r->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame(1, Prospect::filtrar($query['filtros'])->count());
    }
}
