<?php

namespace App\Livewire\Assistente;

use App\Livewire\Central\ProspectChat;
use App\Models\Material;
use App\Models\Part;
use App\Models\Tenant;
use App\Services\ProspectChatService;
use App\Services\SuprimentosChatService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Chat de IA de Suprimentos (estoque, compras, pecas, fornecedores) do app
 * Oravel Mobile (/assistente/suprimentos). Mesma tela e mesma mecanica do chat de
 * Prospects (herda ProspectChat), com 3 diferencas:
 *  - quem usa e' usuario de CLIENTE (tenant) com acesso ao modulo; o super admin
 *    fica so na Central e nao entra aqui;
 *  - os dados sao SEMPRE os do cliente do usuario logado, lido do usuario a cada
 *    acao (nunca de estado do navegador) e imposto por App\Support\Assistente\TenantSql;
 *  - e' so consulta: Excel + relatorio compartilhavel (link/WhatsApp).
 */
class SuprimentosChat extends ProspectChat
{
    protected function autorizar(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && ! $user->isSuperAdmin()
            && filled($user->tenant_id)
            && (Gate::forUser($user)->allows('viewAny', Material::class) || Gate::forUser($user)->allows('viewAny', Part::class)),
            403
        );
    }

    protected function servico(): ProspectChatService
    {
        $this->autorizar(); // toda acao reconfere: o estado do componente vem do navegador

        return app(SuprimentosChatService::class)->paraTenant((string) Auth::user()->tenant_id);
    }

    protected function area(): string
    {
        return 'suprimentos';
    }

    protected function comRelatorio(): bool
    {
        return true;
    }

    protected function limitePorHora(): ?int
    {
        return 60;
    }

    protected function mensagemErro(\Throwable $e): string
    {
        Log::warning('assistente_suprimentos_erro', ['erro' => $e->getMessage()]);

        return 'Não consegui responder agora. Tente de novo em instantes.';
    }

    protected function boasVindas(): string
    {
        return "Olá! Sou o assistente de Suprimentos. Posso consultar estoque, compras, peças e fornecedores da sua empresa, por exemplo:\n\n• **\"Quais peças estão abaixo do estoque mínimo?\"**\n• **\"Quais peças estão faltando nas ordens de serviço e para qual equipamento (patrimônio)?\"**\n• **\"Qual o status da compra de cada peça pedida?\"**\n• **\"Quanto gastamos por fornecedor?\"**\n\nSó consulto, não altero nada. Posso gerar uma lista em Excel ou um relatório para compartilhar no WhatsApp.";
    }

    protected function dadosDaTela(): array
    {
        $cliente = Tenant::find(Auth::user()?->tenant_id)?->name;

        return [
            'tituloApp' => 'Suprimentos',
            'subtituloApp' => $cliente ?? 'Estoque e compras',
            'placeholder' => 'Pergunte sobre estoque, compras, peças...',
            'mostrarStats' => false,
            'badge' => 0,
        ];
    }
}
