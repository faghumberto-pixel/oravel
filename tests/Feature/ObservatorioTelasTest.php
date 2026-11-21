<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ObservatorioEnvioResource\Pages\ListObservatorioEnvios;
use App\Filament\Central\Resources\ProspectResource\Pages\EditProspect;
use App\Jobs\EnviarEventoObservatorio;
use App\Models\ObservatorioEnvio;
use App\Models\Prospect;
use App\Models\ProspectContato;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Abre de verdade as telas da integracao com o Observatorio. O Filament resolve parametros de closure pelo NOME
 * ($state, $record, $data, $get): um nome errado so' estoura ao renderizar, e testes de servico nao renderizam.
 */
class ObservatorioTelasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        config(['observatorio.url' => 'https://obs.test/api/v1', 'observatorio.token' => 'tok', 'observatorio.segredo' => 'seg', 'observatorio.ref_key' => 'k']);
        Cache::flush();
        Queue::fake();
    }

    private function prospect(): Prospect
    {
        return Prospect::create(['cnpj' => '11222333000181', 'nome' => 'Locadora Teste', 'porte' => 'ME', 'atividade' => 'Locação']);
    }

    private function pesquisaFake(): void
    {
        Http::fake(['obs.test/*' => Http::response(['pesquisas' => [['id' => 7, 'versao' => 3, 'titulo' => 'Desafios 2027', 'perguntas' => [
            ['id' => 1, 'texto' => 'Qual o maior desafio?', 'tipo' => 'aberta', 'opcoes' => null, 'obrigatoria' => true, 'ordem' => 1],
            ['id' => 2, 'texto' => 'Possui oficina?', 'tipo' => 'unica', 'opcoes' => ['Sim', 'Não'], 'obrigatoria' => false, 'ordem' => 2],
            ['id' => 3, 'texto' => 'Áreas a melhorar?', 'tipo' => 'multipla', 'opcoes' => ['Manutenção', 'Financeiro'], 'obrigatoria' => false, 'ordem' => 3],
            ['id' => 4, 'texto' => 'Nota?', 'tipo' => 'escala', 'opcoes' => null, 'obrigatoria' => false, 'ordem' => 4],
        ]]]], 200)]);
    }

    public function test_lista_de_envios_renderiza_com_todos_os_status(): void
    {
        $p = $this->prospect();
        $criar = fn (string $status, ?array $payload = null) => ObservatorioEnvio::create([
            'entrevista_ref' => (string) Str::uuid(), 'prospect_id' => $p->id, 'pesquisa_id' => 7, 'pesquisa_titulo' => 'Desafios',
            'status' => $status, 'payload' => $payload, 'ultimo_erro' => 'HTTP 503 erro longo '.str_repeat('x', 100),
        ]);
        $registros = collect(array_keys(ObservatorioEnvio::STATUS))->map(fn ($s) => $criar($s, $s === 'falhou' ? ['x' => 1] : null));

        Livewire::test(ListObservatorioEnvios::class)->assertSuccessful()->assertCanSeeTableRecords($registros);
    }

    public function test_reenviar_so_aparece_para_falhou_e_recoloca_na_fila(): void
    {
        $p = $this->prospect();
        $falhou = ObservatorioEnvio::create(['entrevista_ref' => (string) Str::uuid(), 'prospect_id' => $p->id, 'pesquisa_id' => 7, 'status' => 'falhou', 'tentativas' => 8, 'payload' => ['x' => 1]]);
        $enviado = ObservatorioEnvio::create(['entrevista_ref' => (string) Str::uuid(), 'prospect_id' => $p->id, 'pesquisa_id' => 7, 'status' => 'enviado']);

        Livewire::test(ListObservatorioEnvios::class)
            ->assertTableActionHidden('reenviar', $enviado)
            ->assertTableActionVisible('reenviar', $falhou)
            ->callTableAction('reenviar', $falhou)->assertHasNoTableActionErrors();

        $this->assertSame(['pendente', 0], [$falhou->fresh()->status, $falhou->fresh()->tentativas]);
        Queue::assertPushed(EnviarEventoObservatorio::class, fn ($j) => $j->envioId === $falhou->id);
    }

    public function test_acao_registrar_entrevista_no_prospect_abre_e_registra(): void
    {
        $this->pesquisaFake();
        $p = $this->prospect();

        Livewire::test(EditProspect::class, ['record' => $p->id])->assertSuccessful()
            ->callAction('registrarEntrevista', [
                'pesquisa_id' => 7, 'contato_nome' => 'José', 'uf' => 'sp', 'canal' => 'telefone', 'observacao_livre' => 'Falta mecânico',
                'respostas' => ['1' => 'Mão de obra', '2' => 'Sim', '3' => ['Manutenção'], '4' => '4'],
            ])->assertHasNoActionErrors();

        $e = ObservatorioEnvio::sole();
        $this->assertSame('pendente', $e->status);
        $this->assertSame(7, $e->pesquisa_id);
        $this->assertSame(auth()->id(), $e->user_id, 'user_id e UUID e deve ser o do usuario logado');
        $this->assertSame('SP', $e->payload['data']['segmentacao']['uf']);
        $this->assertCount(4, $e->payload['data']['respostas']);
        $this->assertSame(1, ProspectContato::where('prospect_id', $p->id)->count());
        $this->assertSame('Super', ProspectContato::first()->autor);
        Queue::assertPushed(EnviarEventoObservatorio::class);
    }

    public function test_acao_exige_pergunta_obrigatoria_e_nao_registra_sem_ela(): void
    {
        $this->pesquisaFake();
        $p = $this->prospect();

        Livewire::test(EditProspect::class, ['record' => $p->id])
            ->callAction('registrarEntrevista', ['pesquisa_id' => 7, 'canal' => 'telefone', 'respostas' => ['2' => 'Sim']])
            ->assertHasActionErrors();

        $this->assertSame(0, ObservatorioEnvio::count());
        $this->assertSame(0, ProspectContato::count());
    }

    public function test_acao_com_observatorio_indisponivel_avisa_e_nao_quebra_a_tela(): void
    {
        Http::fake(['obs.test/*' => Http::response('erro', 500)]);
        $p = $this->prospect();

        Livewire::test(EditProspect::class, ['record' => $p->id])->assertSuccessful()->mountAction('registrarEntrevista')->assertSuccessful();

        $this->assertSame(0, ObservatorioEnvio::count());
    }

    public function test_acao_sem_chave_do_pseudonimo_avisa_e_nao_grava_nada(): void
    {
        $this->pesquisaFake();
        config(['observatorio.ref_key' => '']);
        $p = $this->prospect();

        Livewire::test(EditProspect::class, ['record' => $p->id])
            ->callAction('registrarEntrevista', ['pesquisa_id' => 7, 'canal' => 'telefone', 'respostas' => ['1' => 'x']])
            ->assertNotified('Integração com o Observatório incompleta');

        $this->assertSame(0, ObservatorioEnvio::count());
        $this->assertSame(0, ProspectContato::count());
    }
}
