<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Pacotes;
use App\Support\Precos;
use Illuminate\Support\Facades\DB;

/**
 * Upgrade (ou redução) dos produtos de uma empresa, sem migrar dado: os dados são da empresa, não do produto. Troca o contrato
 * (vitrine do produto, ou contrato combinado), recalcula a mensalidade (soma dos valores cheios, desconto da recorrência
 * conforme a forma de pagamento, mais os complementos vigentes) e já envia o novo valor à assinatura no Asaas.
 */
class ProdutosDaEmpresa
{
    public function __construct(private ?ComplementoService $complementos = null) {}

    /**
     * Quanto a empresa passaria a pagar por mês com estes produtos.
     *
     * @param  list<string>  $codigos
     * @return array{cheio: float, mensalidade: float, total: float}
     */
    public function calcular(Tenant $tenant, array $codigos): array
    {
        $cheio = round(collect($this->vitrines($codigos))->sum(fn (Plan $p) => (float) $p->base_price), 2);
        $mensalidade = Precos::paraForma($cheio, $tenant->paymentMethod());

        return ['cheio' => $cheio, 'mensalidade' => $mensalidade, 'total' => round($mensalidade + Tenant::complementosVigentes($tenant), 2)];
    }

    /** @param  list<string>  $codigos */
    public function alterar(Tenant $tenant, array $codigos): Tenant
    {
        $codigos = array_values(array_unique(array_map(fn ($c) => Pacotes::normalizar($c), $codigos)));
        $valores = $this->calcular($tenant, $codigos);
        $antigo = $tenant->plan;

        DB::transaction(function () use ($tenant, $codigos, $valores, $antigo) {
            $vitrines = $this->vitrines($codigos);
            $novo = count($codigos) === 1
                ? $vitrines[$codigos[0]]
                : Pacotes::planoCombinado($vitrines[$codigos[0]], array_slice($codigos, 1));

            // Sem cobrar implantação de novo: ela é de quem contrata a primeira vez.
            $tenant->forceFill(['plan_id' => $novo->id, 'mrr_value' => $valores['total']])->save();

            if ($antigo && $antigo->campaign_tag === Pacotes::TAG_COMBO && $antigo->id !== $novo->id
                && ! Tenant::withoutGlobalScopes()->where('plan_id', $antigo->id)->exists()) {
                $antigo->delete();
            }
        });

        ($this->complementos ?? app(ComplementoService::class))->enviarValor($tenant->refresh());

        return $tenant->refresh();
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, Plan> código => vitrine
     */
    private function vitrines(array $codigos): array
    {
        if ($codigos === []) {
            throw new \InvalidArgumentException('Escolha pelo menos um produto.');
        }

        $vendaveis = Pacotes::vendaveis();
        $vitrines = [];

        foreach ($codigos as $codigo) {
            $vitrines[$codigo] = $vendaveis[$codigo] ?? throw new \InvalidArgumentException('O produto "'.Pacotes::nome($codigo).'" não está à venda (defina o preço na vitrine).');
        }

        return $vitrines;
    }
}
