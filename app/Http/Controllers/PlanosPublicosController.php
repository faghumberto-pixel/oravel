<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\TaxaConfiguracao;
use App\Support\Pacotes;
use App\Support\Precos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Preços das soluções para o site institucional (oravel.com.br), lidos da Central: só vitrines ativas e com valor, sem nenhum dado de
 * cliente. O site nunca guarda valor no HTML; ele pergunta aqui. Cache curto, limpo quando uma vitrine muda (Plan::saved).
 */
class PlanosPublicosController extends Controller
{
    public const CACHE_KEY = 'planos-publicos';

    public function __invoke(Request $request): JsonResponse
    {
        // Sem venda online nenhum valor é divulgado: a API pública responde vazia (o site também não consulta mais).
        if (! Pacotes::vendaOnline()) {
            return response()->json(['data' => [], 'taxas' => []])->header('Cache-Control', 'no-store');
        }

        $pacote = Cache::remember(self::CACHE_KEY, 300, fn () => ['planos' => $this->montar(), 'taxas' => $this->taxas()]);

        // CORS: o middleware global das rotas /api já libera leitura entre origens; aqui só há dado público (nada de cliente).
        return response()->json(['data' => $pacote['planos'], 'taxas' => $pacote['taxas']])->header('Cache-Control', 'public, max-age=300')->header('Vary', 'Origin');
    }

    /** Taxas de configuração (pagamento único, opcionais) à venda: só as ativas e com valor. @return list<array<string, mixed>> */
    private function taxas(): array
    {
        return TaxaConfiguracao::disponiveis()->get()->map(fn (TaxaConfiguracao $t) => [
            'codigo' => $t->codigo, 'nome' => $t->nome, 'descricao' => $t->descricao, 'valor' => (float) $t->valor, 'pagamento' => 'unico',
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function montar(): array
    {
        return Plan::query()->where('campaign_tag', Pacotes::TAG_VITRINE)->where('is_active', true)->where('base_price', '>', 0)->orderBy('name')->get()
            ->filter(fn (Plan $p) => isset(Pacotes::todos()[$p->pacote]))
            ->map(function (Plan $p) {
                $cheio = (float) $p->base_price;
                $implantacao = (float) $p->implementation_fee;

                return [
                    'codigo' => $p->pacote,
                    'nome' => $p->name,
                    'valor_cheio' => $cheio,
                    'valor_recorrente' => Precos::recorrente($cheio),
                    'desconto_percentual' => Precos::descontoPercentual(),
                    'ciclo' => $p->billing_cycle,
                    'checkout_url' => route('checkout.pacote', ['pacote' => $p->pacote]),
                    'implantacao' => $implantacao > 0 ? [
                        'opcional' => (bool) $p->implantacao_opcional,
                        'valor' => $implantacao,
                        'parcelas_max' => max(1, min(2, (int) $p->implementation_installments)),
                        'modo' => $p->implementation_billing_mode === 'somada' ? 'somada' : 'separada',
                    ] : null,
                ];
            })->values()->all();
    }
}
