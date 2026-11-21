<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ProspectResource\Pages\EditProspect;
use App\Models\ObservatorioEnvio;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Producao usa QUEUE_CONNECTION=sync e nao tem worker: o envio ao Observatorio acontece dentro da requisicao de
 * quem registra a entrevista. Aqui a fila e' a REAL (sync), sem fake.
 */
class ObservatorioFilaSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'observatorio.url' => 'https://obs.test/api/v1', 'observatorio.token' => 'tok', 'observatorio.segredo' => 'seg', 'observatorio.ref_key' => 'k']);
        Cache::flush();

        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function pesquisaFake(mixed $eventos): void
    {
        Http::fake([
            'obs.test/api/v1/pesquisas/ativas' => Http::response(['pesquisas' => [['id' => 7, 'versao' => 3, 'titulo' => 'Desafios', 'perguntas' => [
                ['id' => 1, 'texto' => 'Maior desafio?', 'tipo' => 'aberta', 'opcoes' => null, 'obrigatoria' => true, 'ordem' => 1]]]]], 200),
            'obs.test/api/v1/eventos' => $eventos,
        ]);
    }

    private function registrar(Prospect $p)
    {
        return Livewire::test(EditProspect::class, ['record' => $p->id])
            ->callAction('registrarEntrevista', ['pesquisa_id' => 7, 'canal' => 'telefone', 'respostas' => ['1' => 'Mão de obra']]);
    }

    public function test_acao_entrega_na_hora_e_avisa_que_foi_enviada(): void
    {
        $this->pesquisaFake(Http::response(['status' => 'accepted'], 202));
        $p = Prospect::create(['cnpj' => '11222333000181', 'nome' => 'Locadora']);

        $this->registrar($p)->assertNotified('Entrevista registrada');

        $e = ObservatorioEnvio::sole();
        $this->assertSame('enviado', $e->status);
        $this->assertNull($e->payload, 'as respostas nao ficam na Central depois da entrega');
    }

    public function test_acao_com_observatorio_fora_do_ar_nao_estoura_e_deixa_para_reenvio_automatico(): void
    {
        $this->pesquisaFake(Http::response('erro', 503));
        $p = Prospect::create(['cnpj' => '11222333000181', 'nome' => 'Locadora']);

        $this->registrar($p)->assertNotified('Entrevista registrada'); // a tela nao quebra

        $e = ObservatorioEnvio::sole();
        $this->assertSame(['pendente', 1], [$e->status, $e->tentativas]);
        $this->assertNotNull($e->payload);
        $this->assertTrue($e->proxima_tentativa_em->isFuture());
    }
}
