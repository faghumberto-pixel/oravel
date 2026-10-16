<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Services\CnpjLookupService;
use App\Support\Cnpj;
use App\Support\CrmCnpjPreenchimento;
use Illuminate\Http\JsonResponse;

class CnpjController extends Controller
{
    use RespondeCrmApi;

    public function __invoke(string $cnpj): JsonResponse
    {
        if (! Cnpj::valido($cnpj)) {
            return $this->erro('cnpj_invalido', 'CNPJ inválido: confira os 14 dígitos.', 422);
        }

        $dados = app(CnpjLookupService::class)->lookup($cnpj);

        if (! $dados) {
            return $this->erro('cnpj_nao_encontrado', 'A Receita não devolveu dados para este CNPJ agora. Tente novamente em instantes.', 404);
        }

        return $this->ok([
            'documento' => Cnpj::formatar($cnpj),
            'dados' => CrmCnpjPreenchimento::mapear('lead', $dados),
            'duplicidade' => CrmCnpjPreenchimento::duplicidade($cnpj),
        ]);
    }
}
