<?php

namespace App\Services;

use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappNumero;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cadastro incorporado (Embedded Signup) da Meta: o cliente autoriza o próprio número numa janela
 * da Meta e o app da Oravel (Tech Provider) recebe um código. Aqui o código vira o token da empresa,
 * o número é registrado na API, o webhook do app é assinado na conta do cliente e tudo é gravado.
 * Todas as chamadas são servidor a servidor (o segredo do app nunca vai para o navegador).
 */
class WhatsAppCadastroMeta
{
    public static function disponivel(): bool
    {
        return filled(config('services.whatsapp.app_id'))
            && filled(config('services.whatsapp.app_secret'))
            && filled(config('services.whatsapp.embedded_config_id'));
    }

    /**
     * @return WhatsappNumero número conectado e testado
     *
     * @throws \RuntimeException com mensagem em português para mostrar ao usuário
     */
    public function concluir(string $tenantId, string $codigo, string $wabaId, string $phoneNumberId, ?string $userId = null): WhatsappNumero
    {
        if (! static::disponivel()) {
            throw new \RuntimeException('A conexão automática ainda não está liberada pela Oravel.');
        }

        $token = $this->trocarCodigoPorToken($codigo);

        // Habilita o número na API na nuvem (PIN de 6 dígitos novo; a verificação em duas etapas fica com ele).
        $registro = Http::withToken($token)->timeout(30)->post($this->url($phoneNumberId.'/register'), [
            'messaging_product' => 'whatsapp', 'pin' => (string) random_int(100000, 999999),
        ]);
        if (! $registro->successful() && ! str_contains((string) $registro->json('error.message'), 'already')) {
            throw new \RuntimeException($registro->json('error.message') ?? 'A Meta não conseguiu registrar o número.');
        }

        // Assina o webhook do app da Oravel na conta do cliente: sem isso as respostas não chegam.
        $assinatura = Http::withToken($token)->timeout(30)->post($this->url($wabaId.'/subscribed_apps'));
        if (! $assinatura->successful()) {
            throw new \RuntimeException($assinatura->json('error.message') ?? 'A Meta não aceitou ligar o webhook à conta.');
        }

        $config = TenantWhatsappSetting::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId]);
        $config->fill(['waba_id' => $wabaId, 'access_token' => $token, 'app_secret' => null, 'enabled' => true]);
        if (! $config->exists) {
            $config->verify_token = Str::random(32);
        }
        $config->save();

        $numero = WhatsappNumero::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'phone_number_id' => $phoneNumberId],
            ['user_id' => $userId, 'enabled' => true],
        );

        $info = (new WhatsAppEmpresaService($config, $numero))->testarConexao();
        $numero->update(['display_phone' => $info['display_phone_number'] ?? null, 'rotulo' => $numero->rotulo ?: ($info['verified_name'] ?? null), 'last_test_at' => now(), 'last_test_ok' => true, 'last_error' => null]);
        $config->update(['last_test_at' => now(), 'last_test_ok' => true, 'last_error' => null]);

        return $numero->refresh();
    }

    private function trocarCodigoPorToken(string $codigo): string
    {
        $resposta = Http::timeout(30)->get($this->url('oauth/access_token'), [
            'client_id' => config('services.whatsapp.app_id'),
            'client_secret' => config('services.whatsapp.app_secret'),
            'code' => $codigo,
        ]);

        $token = $resposta->json('access_token');

        if (! $resposta->successful() || blank($token)) {
            throw new \RuntimeException($resposta->json('error.message') ?? 'A Meta não aceitou a autorização. Tente conectar de novo.');
        }

        return (string) $token;
    }

    private function url(string $caminho): string
    {
        return rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com/v20.0'), '/').'/'.ltrim($caminho, '/');
    }
}
