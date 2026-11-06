<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;

/**
 * Liga e desliga, POR CLIENTE, as telas de Inteligencia de Ativos (custo de manutencao por equipamento e
 * localizacao no mapa). Usado pela Central (Empresas -> "Inteligencia de Ativos") e preservado quando o cliente
 * troca de produtos. Se o cliente esta num contrato compartilhado (vitrine do pacote ou usado por mais de um
 * cliente), ele ganha uma COPIA exclusiva do contrato antes da mudanca: nenhum outro cliente e' afetado.
 */
class InteligenciaDeAtivosService
{
    /** chave de contrato => nome para exibir */
    public const CHAVES = [
        CustoManutencaoService::FEATURE => 'Custo de manutenção por equipamento (tela e aba "Custos e histórico" no cadastro)',
        LocalizacaoEquipamentosService::FEATURE => 'Localização dos equipamentos (mapa com obra do contrato e rastreador)',
    ];

    /** @return list<string> chaves ligadas no contrato atual do cliente */
    public function ativas(Tenant $tenant): array
    {
        $plano = $tenant->plan;
        if (! $plano) {
            return [];
        }

        return array_values(array_filter(array_keys(self::CHAVES), fn ($chave) => $plano->hasFeature($chave)));
    }

    /** Texto curto para a lista da Central. */
    public function resumo(Tenant $tenant): string
    {
        $ativas = $this->ativas($tenant);

        return match (count($ativas)) {
            0 => 'Desligado',
            count(self::CHAVES) => 'Custo + Localização',
            default => $ativas[0] === CustoManutencaoService::FEATURE ? 'Só custo' : 'Só localização',
        };
    }

    /**
     * Deixa ligadas EXATAMENTE as chaves informadas (as demais do grupo ficam desligadas).
     *
     * @param  list<string>  $chaves
     */
    public function definir(Tenant $tenant, array $chaves): Tenant
    {
        $desejadas = array_values(array_intersect(array_keys(self::CHAVES), $chaves));

        if (! $tenant->plan_id) {
            throw new \InvalidArgumentException('O cliente não tem contrato.');
        }

        $atuais = $this->ativas($tenant);
        if (count($atuais) === count($desejadas) && ! array_diff($atuais, $desejadas)) {
            return $tenant; // nada a mudar: nao cria copia de contrato a toa
        }

        $plano = app(ComplementoService::class)->contratoProprio($tenant);
        $plano->update(['features' => $this->aplicar($plano, $desejadas)]);

        return $tenant->refresh();
    }

    /** Mantem o formato que o contrato ja usa (lista de chaves ou mapa chave => bool). @return array<int|string, mixed> */
    private function aplicar(Plan $plano, array $desejadas): array
    {
        $features = is_array($plano->features) ? $plano->features : [];

        if (array_is_list($features)) {
            $features = array_values(array_diff($features, array_keys(self::CHAVES)));

            return array_values(array_merge($features, $desejadas));
        }

        foreach (array_keys(self::CHAVES) as $chave) {
            $features[$chave] = in_array($chave, $desejadas, true);
        }

        return $features;
    }
}
