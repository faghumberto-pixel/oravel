<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Webhook `subscribers/new` do Sender. O formato do payload e o cabecalho x-sender-hmac-sha256 foram conferidos com
 * uma entrega real (20/11/2026); os DADOS abaixo sao ficticios. A assinatura aqui e' calculada pelo mesmo algoritmo
 * (HMAC-SHA256 hex do corpo cru): prova consistencia do codigo, nao a chave real do Sender.
 * DatabaseTransactions (ver Fase1Test).
 */
class RelacionamentoSenderWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private const SEGREDO = 'segredo-de-teste-do-sender';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.relacionamento.sender_secret' => self::SEGREDO]);
    }

    private function assinante(array $extra = []): array
    {
        return $extra + [
            'bounced_at' => null, 'bounced_at_utc' => null,
            'columns' => [
                ['id' => 'axvX4n', 'title' => 'Empresa', 'type' => 'text', 'value' => 'Locadora Exemplo Ltda'],
                ['id' => 'avAlVe', 'title' => 'First name', 'type' => 'text', 'value' => 'Maria Teste'],
            ],
            'created' => '2026-11-20 00:24:17', 'created_utc' => '2026-11-20 00:24:17',
            'email' => 'Maria.Teste@Exemplo.com', 'firstname' => 'Maria', 'id' => 'SUB0001', 'ip_address' => '',
            'lastname' => 'Teste', 'location' => 'Brazil', 'phone' => '+55 11 98888-0001',
            'phone_country' => ['country_code' => 'BR', 'phone_code' => 55], 'source' => 'FORM',
            'status' => ['email' => 'active', 'sms' => 'active', 'temail' => 'active', 'tsms' => 'active'],
            'subscriber_tags' => [['id' => 'e5rMPv', 'title' => 'Gazeta Watsapp']],
            'unsubscribed_at' => null, 'unsubscribed_at_utc' => null,
        ];
    }

    private function enviar(array|string $corpo, ?string $assinatura = 'auto', string $topico = 'subscribers-new')
    {
        $raw = is_array($corpo) ? json_encode($corpo) : $corpo;
        $headers = ['CONTENT_TYPE' => 'application/json'];
        $sig = $assinatura === 'auto' ? hash_hmac('sha256', $raw, self::SEGREDO) : $assinatura;
        if ($sig !== null) {
            $headers['HTTP_X_SENDER_HMAC_SHA256'] = $sig;
        }

        return $this->call('POST', "/api/relacionamento/sender/{$topico}", [], [], [], $headers, $raw);
    }

    public function test_assinatura_ausente_errada_ou_segredo_vazio_recusam_e_nao_gravam(): void
    {
        $this->enviar($this->assinante(), null)->assertStatus(401);
        $this->enviar($this->assinante(), str_repeat('a', 64))->assertStatus(401);
        $this->enviar($this->assinante(), hash_hmac('sha256', 'outro corpo', self::SEGREDO))->assertStatus(401);

        config(['services.relacionamento.sender_secret' => '']);
        $this->enviar($this->assinante(), hash_hmac('sha256', json_encode($this->assinante()), ''))->assertStatus(401);

        $this->assertSame(0, RelInteracao::count());
    }

    public function test_corpo_alterado_depois_de_assinado_e_recusado(): void
    {
        $raw = json_encode($this->assinante());
        $sig = hash_hmac('sha256', $raw, self::SEGREDO);

        $this->enviar(str_replace('SUB0001', 'SUB9999', $raw), $sig)->assertStatus(401);
        $this->assertSame(0, RelInteracao::count());
    }

    public function test_topico_desconhecido_nao_existe(): void
    {
        $this->enviar($this->assinante(), 'auto', 'campaigns-new')->assertStatus(404);
    }

    public function test_assinante_da_gazeta_vira_contato_e_interacao_com_consentimento(): void
    {
        $this->enviar($this->assinante())->assertOk()->assertJson(['ok' => true, 'registradas' => 1]);

        $c = RelContato::where('email', 'maria.teste@exemplo.com')->firstOrFail();
        $this->assertSame('Maria Teste', $c->nome);
        $this->assertSame('Locadora Exemplo Ltda', $c->empresa_nome);
        $this->assertSame('11988880001', $c->telefone_digitos);
        $this->assertSame('sender-form', $c->consentimento_origem);

        $i = RelInteracao::firstOrFail();
        $this->assertSame('gazeta_inscricao', $i->tipo);
        $this->assertSame('sender', $i->origem);
        $this->assertSame($c->id, $i->contato_id);
        $this->assertSame('2026-11-20 00:24:17', $i->ocorrido_em->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('SUB0001', $i->dados['sender_id']);
    }

    public function test_reentrega_do_mesmo_assinante_nao_duplica(): void
    {
        $this->enviar($this->assinante())->assertOk();
        $this->enviar($this->assinante())->assertOk();

        $this->assertSame(1, RelInteracao::count());
        $this->assertSame(1, RelContato::count());
    }

    public function test_liga_a_empresa_quando_o_email_e_de_um_prospect(): void
    {
        $p = Prospect::create(['cnpj' => '11222333000144', 'nome' => 'Locadora Exemplo', 'email' => 'maria.teste@exemplo.com']);

        $this->enviar($this->assinante())->assertOk();

        $this->assertSame($p->id, RelInteracao::firstOrFail()->prospect_id);
    }

    public function test_tag_de_material_vira_download_e_tag_desconhecida_e_ignorada_sem_erro(): void
    {
        $this->enviar($this->assinante(['id' => 'SUB0002', 'email' => 'b@x.com', 'subscriber_tags' => [['id' => 't1', 'title' => 'Guia Fatura NFS-e']]]))->assertOk();
        $this->assertSame('pdf', RelInteracao::firstOrFail()->tipo);

        $r = $this->enviar($this->assinante(['id' => 'SUB0003', 'email' => 'c@x.com', 'subscriber_tags' => [['id' => 't2', 'title' => 'Tag Qualquer']]]));
        $r->assertOk()->assertJson(['ignorado' => 'sem tag reconhecida']);
        $this->assertSame(1, RelInteracao::count());
        $this->assertNull(RelContato::where('email', 'c@x.com')->first());
    }

    public function test_assinante_nao_vindo_de_formulario_nao_registra_consentimento(): void
    {
        $this->enviar($this->assinante(['source' => 'IMPORT']))->assertOk();

        $this->assertNull(RelContato::firstOrFail()->consentimento_em);
    }

    public function test_corpo_invalido_ou_sem_email_nunca_vira_erro_apos_assinatura_valida(): void
    {
        $this->enviar('{lixo')->assertOk();
        $this->enviar($this->assinante(['email' => null]))->assertOk();
        $this->enviar($this->assinante(['id' => null]))->assertOk();

        $this->assertSame(0, RelInteracao::count());
    }
}
