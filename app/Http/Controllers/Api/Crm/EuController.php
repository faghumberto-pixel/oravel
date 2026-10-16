<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\PropostaComercial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EuController extends Controller
{
    use RespondeCrmApi;

    /** Valida o token e diz quem é o usuário, a empresa e o que ele pode fazer. */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return $this->ok([
            'usuario' => ['id' => $user->id, 'nome' => $user->name, 'email' => $user->email],
            'empresa' => ['id' => $user->tenant->id, 'nome' => $user->tenant->name],
            've_todos_os_leads' => $user->canSeeAllCrmLeads(),
            'permissoes' => [
                'criar_lead' => Gate::forUser($user)->allows('create', CrmLead::class),
                'editar_lead' => Gate::forUser($user)->allows('update', new CrmLead(['tenant_id' => $user->tenant_id])),
                'ver_clientes' => Gate::forUser($user)->allows('viewAny', Client::class),
                'ver_propostas' => Gate::forUser($user)->allows('viewAny', PropostaComercial::class),
            ],
            'token' => [
                'habilidades' => $token?->abilities ?? [],
                'expira_em' => $token?->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
