<?php

namespace App\Services\Fiscal;

use App\Models\TenantFiscalSetting;

/**
 * Emissor falso para testes e para ver as telas sem conta no emissor. Guarda as chamadas em $chamadas e permite simular falha
 * (`$falhar`) e nota recusada (`$recusar`). `$status` é o status devolvido na emissão (processando por padrão, como no emissor real).
 */
class FakeFiscalProvider implements FiscalProvider
{
    /** @var list<array{0: string, 1: array}> */
    public static array $chamadas = [];

    public static ?string $falhar = null;

    public static ?string $recusar = null;

    public static string $status = 'processando';

    public static function reset(): void
    {
        self::$chamadas = [];
        self::$falhar = self::$recusar = null;
        self::$status = 'processando';
    }

    private function registrar(string $metodo, array $dados = []): void
    {
        self::$chamadas[] = [$metodo, $dados];

        if (self::$falhar) {
            throw new FiscalException(self::$falhar);
        }
    }

    public function sincronizarEmpresa(TenantFiscalSetting $c, ?string $certificadoBase64 = null, ?string $senha = null): array
    {
        // O certificado e a senha nunca entram no registro de chamadas: só se foram enviados.
        $this->registrar('sincronizarEmpresa', ['com_certificado' => $certificadoBase64 !== null]);

        return [
            'id' => $c->provider_company_id ?: 'emp_'.substr(md5((string) $c->tenant_id), 0, 8),
            'token_homologacao' => 'tok-h-'.substr(md5((string) $c->tenant_id), 0, 6), 'token_producao' => 'tok-p-'.substr(md5((string) $c->tenant_id), 0, 6),
            'certificado_validade' => $certificadoBase64 !== null ? now()->addYear()->toDateString() : null,
        ];
    }

    public function testar(TenantFiscalSetting $c): void
    {
        $this->registrar('testar');
    }

    public function emitirNfse(TenantFiscalSetting $c, string $ref, array $dados): array
    {
        $this->registrar('emitirNfse', ['ref' => $ref] + $dados);

        if (self::$recusar) {
            return ['status' => 'erro', 'mensagem' => self::$recusar];
        }

        return $this->nota($ref, self::$status);
    }

    public function consultarNfse(TenantFiscalSetting $c, string $ref): array
    {
        $this->registrar('consultarNfse', ['ref' => $ref]);

        return $this->nota($ref, self::$status);
    }

    public function cancelarNfse(TenantFiscalSetting $c, string $ref, string $motivo): array
    {
        $this->registrar('cancelarNfse', ['ref' => $ref, 'motivo' => $motivo]);

        return $this->nota($ref, 'cancelada');
    }

    private function nota(string $ref, string $status): array
    {
        $ok = $status === 'autorizada';

        return [
            'status' => $status, 'numero' => $ok ? (string) (abs(crc32($ref)) % 9000 + 1000) : null, 'serie' => $ok ? '1' : null,
            'codigo_verificacao' => $ok ? strtoupper(substr(md5($ref), 0, 8)) : null,
            'pdf_url' => $ok ? 'https://fake.test/nfse/'.$ref.'.pdf' : null, 'xml_url' => $ok ? 'https://fake.test/nfse/'.$ref.'.xml' : null,
            'emitida_em' => $ok ? now()->toIso8601String() : null, 'mensagem' => null,
        ];
    }
}
