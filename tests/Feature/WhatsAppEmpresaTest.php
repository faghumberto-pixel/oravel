<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantWhatsappSetting;
use App\Models\User;
use App\Models\WhatsappConversa;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use App\Services\WhatsAppEmpresaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $nome = 'Locadora'): Tenant
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['modulo_whatsapp' => true]]);

        return Tenant::create(['name' => $nome.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function config(Tenant $tenant, bool $ativo = true): TenantWhatsappSetting
    {
        return TenantWhatsappSetting::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'enabled' => $ativo, 'access_token' => 'TOKEN-SECRETO',
            'app_secret' => 'segredo-do-app', 'verify_token' => 'verifica-123', 'template_abertura' => 'abertura', 'template_proposta' => 'proposta_enviada',
        ]);
    }

    private function numero(Tenant $tenant, ?User $usuario = null, string $phoneNumberId = '111222333'): WhatsappNumero
    {
        return WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'user_id' => $usuario?->id, 'phone_number_id' => $phoneNumberId, 'display_phone' => '+55 19 90000-0000']);
    }

    private function usuario(Tenant $tenant, string $nome): User
    {
        return User::create(['name' => $nome, 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
    }

    private function postarWebhook(Tenant $tenant, array $payload, ?string $segredo = 'segredo-do-app')
    {
        $json = json_encode($payload);
        $assinatura = 'sha256='.hash_hmac('sha256', $json, (string) $segredo);

        return $this->call('POST', '/api/webhooks/whatsapp-empresa/'.$tenant->id, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $assinatura], $json);
    }

    private function mensagemRecebida(string $de, string $texto, string $id = 'wamid.IN1', string $numeroId = '111222333'): array
    {
        return ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $numeroId],
            'contacts' => [['wa_id' => $de, 'profile' => ['name' => 'João do Cliente']]],
            'messages' => [['from' => $de, 'id' => $id, 'type' => 'text', 'text' => ['body' => $texto]]],
        ]]]]]];
    }

    public function test_handshake_da_meta_confere_o_token_da_empresa(): void
    {
        $e = $this->empresa();
        $this->config($e);

        $this->get('/api/webhooks/whatsapp-empresa/'.$e->id.'?hub_mode=subscribe&hub_verify_token=verifica-123&hub_challenge=ABC')->assertOk()->assertSee('ABC');
        $this->get('/api/webhooks/whatsapp-empresa/'.$e->id.'?hub_mode=subscribe&hub_verify_token=errado&hub_challenge=ABC')->assertForbidden();
    }

    public function test_recebe_mensagem_cria_conversa_liga_ao_cliente_e_nao_duplica(): void
    {
        $e = $this->empresa();
        $this->config($e);
        $this->numero($e);
        $cliente = Client::create(['tenant_id' => $e->id, 'name' => 'Construtora Alfa', 'email' => 'a@a.test', 'whatsapp' => '(19) 99933-2615']);

        $this->postarWebhook($e, $this->mensagemRecebida('5519999332615', 'Bom dia, quero uma proposta'))->assertOk();
        $this->postarWebhook($e, $this->mensagemRecebida('5519999332615', 'Bom dia, quero uma proposta'))->assertOk(); // reenvio da Meta

        $conversa = WhatsappConversa::withoutGlobalScopes()->where('tenant_id', $e->id)->firstOrFail();
        $this->assertSame($cliente->id, $conversa->client_id);
        $this->assertSame(1, $conversa->nao_lidas);
        $this->assertTrue($conversa->janelaAberta());
        $this->assertSame(1, WhatsappMensagem::withoutGlobalScopes()->where('conversa_id', $conversa->id)->count());
        $this->assertSame('Bom dia, quero uma proposta', WhatsappMensagem::withoutGlobalScopes()->where('conversa_id', $conversa->id)->value('corpo'));
    }

    public function test_assinatura_errada_ou_empresa_sem_cadastro_e_recusada(): void
    {
        $e = $this->empresa();
        $this->config($e);
        $outra = $this->empresa();

        $this->postarWebhook($e, $this->mensagemRecebida('5519999990000', 'oi'), segredo: 'segredo-errado')->assertForbidden();
        $this->postarWebhook($outra, $this->mensagemRecebida('5519999990000', 'oi'))->assertForbidden();
        $this->assertSame(0, WhatsappConversa::withoutGlobalScopes()->count());
    }

    public function test_status_da_meta_atualiza_a_mensagem_enviada(): void
    {
        $e = $this->empresa();
        $this->config($e);
        $n = $this->numero($e);
        $conversa = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $e->id, 'numero_id' => $n->id, 'telefone' => '5519999990000']);
        $m = WhatsappMensagem::withoutGlobalScopes()->create(['tenant_id' => $e->id, 'conversa_id' => $conversa->id, 'direcao' => 'saida', 'corpo' => 'x', 'wa_id' => 'wamid.OUT1', 'status' => 'enviada']);

        $payload = fn (string $status) => ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.OUT1', 'status' => $status]]]]]]]];

        $this->postarWebhook($e, $payload('read'));
        $this->assertSame('lida', $m->fresh()->status);

        $this->postarWebhook($e, $payload('delivered')); // evento atrasado não faz o status andar para trás
        $this->assertSame('lida', $m->fresh()->status);
    }

    public function test_responde_dentro_da_janela_envia_e_fora_da_janela_recusa(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.NOVO']]], 200)]);
        $e = $this->empresa();
        $n = $this->numero($e);
        $servico = new WhatsAppEmpresaService($this->config($e), $n);

        $aberta = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $e->id, 'numero_id' => $n->id, 'telefone' => '5519999990001', 'ultima_recebida_em' => now()->subHours(2)]);
        $enviada = $servico->enviarTexto($aberta, 'Olá, segue a proposta');
        $this->assertSame('enviada', $enviada->status);
        $this->assertSame('wamid.NOVO', $enviada->wa_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/111222333/messages') && $r->hasHeader('Authorization', 'Bearer TOKEN-SECRETO') && $r['to'] === '5519999990001' && $r['text']['body'] === 'Olá, segue a proposta');

        $fechada = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $e->id, 'numero_id' => $n->id, 'telefone' => '5519999990002', 'ultima_recebida_em' => now()->subHours(30)]);
        $recusada = $servico->enviarTexto($fechada, 'Oi de novo');
        $this->assertSame('falhou', $recusada->status);
        $this->assertStringContainsString('24 horas', $recusada->erro);
        Http::assertSentCount(1);
    }

    public function test_modelo_abre_conversa_a_qualquer_hora_e_erro_da_meta_fica_registrado(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::sequence()->push(['messages' => [['id' => 'wamid.T1']]], 200)->push(['error' => ['message' => 'Template não aprovado']], 400)]);
        $e = $this->empresa();
        $servico = new WhatsAppEmpresaService($this->config($e), $this->numero($e));
        $conversa = $servico->conversa('(19) 99999-0003', 'Maria');

        $ok = $servico->enviarModelo($conversa, 'proposta_enviada', ['Maria', 'https://app.oravel.com.br/p/abc']);
        $this->assertSame('enviada', $ok->status);
        Http::assertSent(fn ($r) => ($r['template']['name'] ?? null) === 'proposta_enviada' && $r['template']['components'][0]['parameters'][1]['text'] === 'https://app.oravel.com.br/p/abc');

        $erro = $servico->enviarModelo($conversa, 'outro_modelo', ['Maria']);
        $this->assertSame('falhou', $erro->status);
        $this->assertSame('Template não aprovado', $erro->erro);
    }

    public function test_telefone_e_normalizado_e_token_fica_criptografado_no_banco(): void
    {
        $this->assertSame('5519999332615', WhatsAppEmpresaService::normalizarTelefone('(19) 99933-2615'));
        $this->assertSame('5519999332615', WhatsAppEmpresaService::normalizarTelefone('+55 19 99933-2615'));

        $e = $this->empresa();
        $c = $this->config($e);
        $this->assertSame('TOKEN-SECRETO', $c->fresh()->access_token);
        $this->assertStringNotContainsString('TOKEN-SECRETO', (string) \DB::table('tenant_whatsapp_settings')->where('id', $c->id)->value('access_token'));
    }

    public function test_cada_usuario_usa_o_proprio_numero_e_recebe_so_as_proprias_conversas(): void
    {
        $e = $this->empresa();
        $this->config($e);
        $ana = $this->usuario($e, 'Ana');
        $bruno = $this->usuario($e, 'Bruno');
        $numAna = $this->numero($e, $ana, 'NUM-ANA');
        $numBruno = $this->numero($e, $bruno, 'NUM-BRUNO');

        // o mesmo cliente escreve para os dois números
        $this->postarWebhook($e, $this->mensagemRecebida('5519999990100', 'oi Ana', 'wamid.A', 'NUM-ANA'))->assertOk();
        $this->postarWebhook($e, $this->mensagemRecebida('5519999990100', 'oi Bruno', 'wamid.B', 'NUM-BRUNO'))->assertOk();

        $conversas = WhatsappConversa::withoutGlobalScopes()->where('tenant_id', $e->id)->get();
        $this->assertCount(2, $conversas);
        $this->assertSame($ana->id, $conversas->firstWhere('numero_id', $numAna->id)->responsavel_user_id);
        $this->assertSame($bruno->id, $conversas->firstWhere('numero_id', $numBruno->id)->responsavel_user_id);

        // aviso vai só para o dono do número
        $this->assertSame(1, $ana->notifications()->count());
        $this->assertSame(1, $bruno->notifications()->count());

        // o envio usa o número do usuário, ou o da empresa quando ele não tem
        $this->assertSame($numAna->id, WhatsAppEmpresaService::paraUsuario($ana)->numero()->id);
        $semNumero = $this->usuario($e, 'Carla');
        $this->assertNull(WhatsAppEmpresaService::paraUsuario($semNumero), 'sem número próprio nem da empresa: usa o link wa.me');
        $empresaNum = $this->numero($e, null, 'NUM-EMPRESA');
        $this->assertSame($empresaNum->id, WhatsAppEmpresaService::paraUsuario($semNumero)->numero()->id);
    }
}
