<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ProspectResource\Pages\EditProspect;
use App\Models\Prospect;
use App\Models\ProspectContato;
use App\Models\User;
use App\Relacionamento\Filament\RelationManagers\RelacionamentoRelationManager;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelConteudo;
use App\Relacionamento\Models\RelInteracao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Relacionamento Oravel, Fase 1: endpoint de eventos, resolucao de identidade e backfill.
 * DatabaseTransactions (nunca RefreshDatabase): config/database.php fixa pgsql, ver InboundLeadTest.
 */
class RelacionamentoFase1Test extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'token-rel-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.relacionamento.channels' => ['oravel-org' => ['token' => self::TOKEN]]]);
    }

    private function evento(array $body, ?string $token = self::TOKEN)
    {
        return $this->postJson('/api/relacionamento/eventos', $body, $token ? ['X-Channel-Token' => $token] : []);
    }

    private function prospect(array $extra = []): Prospect
    {
        return Prospect::create($extra + [
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'nome' => 'Locadora Teste', 'telefone' => '(19) 98888-7777', 'email' => 'dono@locadora.com',
        ]);
    }

    public function test_sem_token_ou_canal_desligado_responde_401(): void
    {
        $this->evento(['tipo' => 'artigo', 'email' => 'a@b.com'], null)->assertStatus(401);
        $this->evento(['tipo' => 'artigo', 'email' => 'a@b.com'], 'errado')->assertStatus(401);

        config(['services.relacionamento.channels' => ['oravel-org' => ['token' => '']]]);
        $this->evento(['tipo' => 'artigo', 'email' => 'a@b.com'], '')->assertStatus(401);
        $this->assertSame(0, RelInteracao::count());
    }

    public function test_exige_email_ou_telefone_e_tipo_valido(): void
    {
        $this->evento(['tipo' => 'artigo'])->assertStatus(422);
        $this->evento(['tipo' => 'inventado', 'email' => 'a@b.com'])->assertStatus(422);
    }

    public function test_evento_cria_contato_interacao_e_catalogo(): void
    {
        $this->evento([
            'tipo' => 'ferramenta', 'email' => 'Maria@Exemplo.com', 'nome' => 'Maria', 'empresa' => 'Exemplo Ltda',
            'conteudo_slug' => 'ferramentas/calculadora-rentabilidade', 'titulo' => 'Calculadora de Rentabilidade',
            'resultado' => 'Margem 18%', 'duracao_segundos' => 95, 'estado' => 'SP', 'visitor_token' => 'vt1',
            'utm_source' => 'google', 'consentimento' => true,
        ])->assertStatus(201);

        $c = RelContato::where('email', 'maria@exemplo.com')->firstOrFail();
        $this->assertSame('Maria', $c->nome);
        $this->assertSame(['vt1'], $c->visitor_tokens);
        $this->assertNotNull($c->consentimento_em);

        $i = RelInteracao::firstOrFail();
        $this->assertSame($c->id, $i->contato_id);
        $this->assertSame('oravel-org', $i->origem);
        $this->assertSame(95, $i->duracao_segundos);
        $this->assertSame('ferramenta', RelConteudo::where('slug', 'ferramentas/calculadora-rentabilidade')->value('tipo'));
    }

    public function test_reenvio_com_mesmo_evento_id_nao_duplica(): void
    {
        $body = ['tipo' => 'artigo', 'email' => 'a@b.com', 'evento_id' => 'ev-1'];
        $this->evento($body)->assertStatus(201);
        $this->evento($body)->assertStatus(200);
        $this->assertSame(1, RelInteracao::count());
    }

    public function test_pessoa_com_email_do_prospect_liga_na_empresa(): void
    {
        $p = $this->prospect();
        $this->evento(['tipo' => 'video', 'email' => 'DONO@locadora.com'])->assertStatus(201);

        $this->assertSame($p->id, RelInteracao::firstOrFail()->prospect_id);
        $this->assertSame(1, $p->relInteracoes()->count());
    }

    public function test_telefone_formatado_diferente_casa_pelos_digitos(): void
    {
        $p = $this->prospect(['email' => null]);
        $this->evento(['tipo' => 'pdf', 'telefone' => '+55 19 98888-7777'])->assertStatus(201);

        $this->assertSame($p->id, RelInteracao::firstOrFail()->prospect_id);
    }

    public function test_casamento_ambiguo_nao_liga_a_nenhuma_empresa(): void
    {
        $this->prospect(['email' => 'mesmo@x.com', 'telefone' => '(11) 90000-0001']);
        $this->prospect(['email' => 'mesmo@x.com', 'telefone' => '(11) 90000-0002']);
        $this->evento(['tipo' => 'artigo', 'email' => 'mesmo@x.com'])->assertStatus(201);

        $this->assertNull(RelInteracao::firstOrFail()->prospect_id);
    }

    public function test_evento_posterior_nao_sobrescreve_dado_do_contato(): void
    {
        $this->evento(['tipo' => 'artigo', 'email' => 'a@b.com', 'nome' => 'Original'])->assertStatus(201);
        $this->evento(['tipo' => 'video', 'email' => 'a@b.com', 'nome' => 'Outro', 'cidade' => 'Campinas'])->assertStatus(201);

        $c = RelContato::where('email', 'a@b.com')->firstOrFail();
        $this->assertSame('Original', $c->nome);
        $this->assertSame('Campinas', $c->cidade);
        $this->assertSame(1, RelContato::count());
    }

    public function test_backfill_importa_contatos_do_prospect_e_e_idempotente(): void
    {
        $p = $this->prospect();
        ProspectContato::create(['prospect_id' => $p->id, 'data' => '2026-11-10', 'canal' => 'whatsapp', 'titulo' => 'Primeiro contato', 'texto' => 'Enviei a mensagem']);

        $this->artisan('relacionamento:backfill')->assertSuccessful();
        $this->artisan('relacionamento:backfill')->assertSuccessful();

        $i = RelInteracao::where('prospect_id', $p->id)->get();
        $this->assertCount(1, $i);
        $this->assertSame('contato_comercial', $i[0]->tipo);
        $this->assertSame('Enviei a mensagem', $i[0]->resultado);
        $this->assertSame('2026-11-10', $i[0]->ocorrido_em->format('Y-m-d'));
    }

    public function test_dry_run_nao_grava(): void
    {
        $p = $this->prospect();
        ProspectContato::create(['prospect_id' => $p->id, 'data' => '2026-11-10', 'canal' => 'email', 'texto' => 'x']);

        $this->artisan('relacionamento:backfill --dry-run')->assertSuccessful();
        $this->assertSame(0, RelInteracao::count());
    }

    public function test_aba_relacionamento_lista_a_timeline_da_empresa_em_ordem(): void
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        $p = $this->prospect();
        $outra = $this->prospect();
        $antiga = RelInteracao::create(['prospect_id' => $p->id, 'tipo' => 'artigo', 'titulo' => 'Antiga', 'ocorrido_em' => now()->subDays(5)]);
        $nova = RelInteracao::create(['prospect_id' => $p->id, 'tipo' => 'ferramenta', 'titulo' => 'Nova', 'ocorrido_em' => now()]);
        $alheia = RelInteracao::create(['prospect_id' => $outra->id, 'tipo' => 'video', 'titulo' => 'Alheia', 'ocorrido_em' => now()]);

        Livewire::actingAs($u)->test(RelacionamentoRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditProspect::class])
            ->assertCanSeeTableRecords([$nova, $antiga], inOrder: true)
            ->assertCanNotSeeTableRecords([$alheia]);
    }

    public function test_ocorrido_em_com_fuso_nao_desloca_o_horario(): void
    {
        $this->evento(['tipo' => 'artigo', 'email' => 'a@b.com', 'ocorrido_em' => '2026-11-19T10:00:00Z'])->assertStatus(201);

        $this->assertSame('2026-11-19 10:00:00', RelInteracao::firstOrFail()->ocorrido_em->utc()->format('Y-m-d H:i:s'));
    }
}
