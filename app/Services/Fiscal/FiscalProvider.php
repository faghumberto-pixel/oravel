<?php

namespace App\Services\Fiscal;

use App\Models\TenantFiscalSetting;

/**
 * Emissor fiscal (Focus NFe na produção; FakeFiscalProvider nos testes). Os retornos de nota seguem sempre o mesmo formato:
 * ['status' => processando|autorizada|erro|cancelada, 'numero','serie','codigo_verificacao','pdf_url','xml_url','emitida_em','mensagem'].
 */
interface FiscalProvider
{
    /**
     * Cadastra (ou atualiza) a empresa no emissor, opcionalmente com o certificado A1 em base64 + senha (repassados, nunca guardados).
     *
     * @return array{id: string, token_homologacao: ?string, token_producao: ?string, certificado_validade: ?string}
     *
     * @throws FiscalException
     */
    public function sincronizarEmpresa(TenantFiscalSetting $config, ?string $certificadoBase64 = null, ?string $senha = null): array;

    /** Confere que a empresa existe no emissor e que o token do ambiente funciona. @throws FiscalException */
    public function testar(TenantFiscalSetting $config): void;

    /** @throws FiscalException */
    public function emitirNfse(TenantFiscalSetting $config, string $ref, array $dados): array;

    /** @throws FiscalException */
    public function consultarNfse(TenantFiscalSetting $config, string $ref): array;

    /** @throws FiscalException */
    public function cancelarNfse(TenantFiscalSetting $config, string $ref, string $motivo): array;
}
