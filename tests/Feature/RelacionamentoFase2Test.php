<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ProspectResource\Pages\EditProspect;
use App\Models\Prospect;
use App\Models\User;
use App\Relacionamento\Filament\RelationManagers\ConteudosConsumidosRelationManager;
use App\Relacionamento\Filament\RelationManagers\RelacionamentoRelationManager;
use App\Relacionamento\Filament\Resources\RelAcaoRegraResource;
use App\Relacionamento\Filament\Resources\RelAcaoRegraResource\Pages\ManageRelAcaoRegras;
use App\Relacionamento\Filament\Resources\RelScoreRegraResource;
use App\Relacionamento\Filament\Resources\RelScoreRegraResource\Pages\ManageRelScoreRegras;
use App\Relacionamento\Models\RelAcaoRegra;
use App\Relacionamento\Models\RelConteudo;
use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Models\RelInteresse;
use App\Relacionamento\Models\RelScoreRegra;
use App\Relacionamento\Services\CalculaScore;
use App\Relacionamento\Services\ProximaAcao;
use App\Relacionamento\Services\SugereInteresses;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Relacionamento Oravel, Fase 2: score, faixas, proxima acao, interesses e abas. DatabaseTransactions (ver Fase1Test). */
class RelacionamentoFase2Test extends TestCase
{
    use DatabaseTransactions;

    private function prospect(): Prospect
    {
        return Prospect::create(['cnpj' => (string) random_int(10000000000000, 99999999999999), 'nome' => 'Locadora X']);
    }

    private function inter(Prospect $p, string $tipo, int $n = 1, int $diasAtras = 1, array $extra = []): void
    {
        for ($i = 0; $i < $n; $i++) {
            RelInteracao::create(['prospect_id' => $p->id, 'tipo' => $tipo, 'ocorrido_em' => now()->subDays($diasAtras)] + $extra);
        }
    }

    private function score(Prospect $p): array
    {
        return app(CalculaScore::class)->calcular($p->relInteracoes());
    }

    public function test_score_soma_pesos_padrao(): void
    {
        $p = $this->prospect();
        $this->inter($p, 'ferramenta');
        $this->inter($p, 'video');
        $this->inter($p, 'pdf');

        $s = $this->score($p);
        $this->assertSame(45, $s['score']);
        $this->assertSame('Médio', $s['faixa']);
        $this->assertEquals(['ferramenta' => 20, 'video' => 10, 'pdf' => 15], $s['detalhe']);
    }

    public function test_repeticao_conta_so_ate_o_limite(): void
    {
        $p = $this->prospect();
        $this->inter($p, 'ferramenta', 10); // limite 3 x 20

        $this->assertSame(60, $this->score($p)['score']);
    }

    public function test_score_nunca_passa_de_100(): void
    {
        $p = $this->prospect();
        foreach (['ferramenta', 'reuniao_solicitada', 'gazeta_inscricao', 'whatsapp_resposta'] as $t) {
            $this->inter($p, $t, 3);
        }

        $this->assertSame(100, $this->score($p)['score']);
    }

    public function test_interacao_fora_da_janela_nao_conta(): void
    {
        $p = $this->prospect();
        $this->inter($p, 'ferramenta', 1, 200);
        $this->inter($p, 'video', 1, 10);

        $this->assertSame(10, $this->score($p)['score']);
    }

    public function test_contato_comercial_nao_pontua(): void
    {
        $p = $this->prospect();
        $this->inter($p, 'contato_comercial', 5);

        $this->assertSame(0, $this->score($p)['score']);
    }

    public function test_peso_editado_e_regra_inativa_mudam_o_score(): void
    {
        $p = $this->prospect();
        $this->inter($p, 'ferramenta');
        $this->inter($p, 'video');

        RelScoreRegra::where('tipo', 'ferramenta')->update(['pontos' => 40]);
        RelScoreRegra::where('tipo', 'video')->update(['ativo' => false]);

        $this->assertSame(40, $this->score($p)['score']);
    }

    public function test_faixas(): void
    {
        $c = app(CalculaScore::class);
        $this->assertSame('Excelente', $c->faixa(80)[0]);
        $this->assertSame('Bom', $c->faixa(79)[0]);
        $this->assertSame('Bom', $c->faixa(60)[0]);
        $this->assertSame('Médio', $c->faixa(59)[0]);
        $this->assertSame('Médio', $c->faixa(30)[0]);
        $this->assertSame('Baixo', $c->faixa(29)[0]);
        $this->assertSame('Baixo', $c->faixa(0)[0]);
    }

    public function test_proxima_acao_por_faixa_e_regra_inativa_ignorada(): void
    {
        $a = app(ProximaAcao::class);
        $this->assertSame('Sugerir reunião', $a->para(85)->titulo);
        $this->assertSame('Sugerir reunião', $a->para(80)->titulo);
        $this->assertSame('Enviar vídeo', $a->para(70)->titulo);
        $this->assertSame('Enviar artigo', $a->para(45)->titulo);
        $this->assertSame('Enviar ferramenta', $a->para(10)->titulo);

        RelAcaoRegra::where('titulo', 'Enviar ferramenta')->update(['ativo' => false]);
        $this->assertNull($a->para(10));
    }

    public function test_sugere_interesses_pelo_tema_dos_conteudos_so_da_empresa(): void
    {
        $p = $this->prospect();
        $outra = $this->prospect();
        RelConteudo::create(['tipo' => 'artigo', 'slug' => 'a1', 'titulo' => 'A1', 'tema' => 'Manutenção']);
        RelConteudo::create(['tipo' => 'video', 'slug' => 'v1', 'titulo' => 'V1', 'tema' => 'Manutenção']);
        RelConteudo::create(['tipo' => 'ferramenta', 'slug' => 'f1', 'titulo' => 'F1', 'tema' => 'Financeiro']);
        RelConteudo::create(['tipo' => 'artigo', 'slug' => 'sem-tema', 'titulo' => 'S']);
        $this->inter($p, 'artigo', 1, 1, ['conteudo_slug' => 'a1']);
        $this->inter($p, 'video', 1, 1, ['conteudo_slug' => 'v1']);
        $this->inter($p, 'ferramenta', 1, 1, ['conteudo_slug' => 'f1']);
        $this->inter($p, 'artigo', 1, 1, ['conteudo_slug' => 'sem-tema']);
        $this->inter($outra, 'artigo', 3, 1, ['conteudo_slug' => 'f1']);

        $this->assertSame(['Manutenção' => 2, 'Financeiro' => 1], app(SugereInteresses::class)->para($p->relInteracoes()));
    }

    public function test_definir_interesses_adiciona_remove_e_ignora_tema_invalido(): void
    {
        $p = $this->prospect();
        RelInteresse::definir($p, ['IA', 'Fiscal', 'Inventado']);
        $this->assertSame(['Fiscal', 'IA'], RelInteresse::marcados($p));

        RelInteresse::definir($p, ['IA', 'Locação']);
        $this->assertSame(['IA', 'Locação'], RelInteresse::marcados($p));
    }

    public function test_endpoint_guarda_o_tema_do_conteudo(): void
    {
        config(['services.relacionamento.channels' => ['oravel-org' => ['token' => 't']]]);
        $this->postJson('/api/relacionamento/eventos', ['tipo' => 'artigo', 'email' => 'a@b.com', 'conteudo_slug' => 'art-1', 'tema' => 'Fiscal'], ['X-Channel-Token' => 't'])->assertStatus(201);
        $this->assertSame('Fiscal', RelConteudo::where('slug', 'art-1')->value('tema'));

        $this->postJson('/api/relacionamento/eventos', ['tipo' => 'artigo', 'email' => 'a@b.com', 'tema' => 'Inventado'], ['X-Channel-Token' => 't'])->assertStatus(422);
    }

    private function superAdmin(): User
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return User::factory()->create(['email' => 'equipe@oravel.test']);
    }

    public function test_aba_mostra_resumo_e_salva_interesses(): void
    {
        $u = $this->superAdmin();
        $p = $this->prospect();
        $this->inter($p, 'ferramenta', 1, 1);
        $this->inter($p, 'pdf', 1, 1);

        $t = Livewire::actingAs($u)->test(RelacionamentoRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditProspect::class]);
        $resumo = $t->instance()->resumo();
        $this->assertStringContainsString('Relacionamento: 35/100 (Médio)', $resumo);
        $this->assertStringContainsString('Próxima ação: Enviar artigo', $resumo);

        $t->callTableAction('interesses', data: ['interesses' => ['IA', 'Fiscal']])->assertHasNoTableActionErrors();
        $this->assertSame(['Fiscal', 'IA'], RelInteresse::marcados($p));
        $this->assertStringContainsString('Interesses: Fiscal, IA', $t->instance()->resumo());
    }

    public function test_aba_conteudos_consumidos_so_lista_conteudo(): void
    {
        $u = $this->superAdmin();
        $p = $this->prospect();
        $this->inter($p, 'artigo', 1, 1, ['titulo' => 'Lido']);
        $this->inter($p, 'contato_comercial', 1, 1, ['titulo' => 'Ligacao']);
        $lido = RelInteracao::where('titulo', 'Lido')->first();
        $lig = RelInteracao::where('titulo', 'Ligacao')->first();

        Livewire::actingAs($u)->test(ConteudosConsumidosRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditProspect::class])
            ->assertCanSeeTableRecords([$lido])->assertCanNotSeeTableRecords([$lig]);
    }

    public function test_telas_de_configuracao_so_para_super_admin(): void
    {
        $u = $this->superAdmin();
        Livewire::actingAs($u)->test(ManageRelScoreRegras::class)->assertSuccessful();
        Livewire::actingAs($u)->test(ManageRelAcaoRegras::class)->assertSuccessful();

        $comum = User::factory()->create(['email' => 'comum@x.test']);
        $this->actingAs($comum);
        $this->assertFalse(RelScoreRegraResource::canViewAny());
        $this->assertFalse(RelAcaoRegraResource::canViewAny());
    }
}
