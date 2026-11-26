<?php

namespace App\Livewire\Assistente;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Trial\Services\CotaDiariaIa;
use App\Livewire\Central\ProspectChat;
use App\Models\Tenant;
use App\Services\IntelligenceChatService;
use App\Services\ProspectChatService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Chat de IA do Oravel Intelligence (/assistente/intelligence). Mesma tela e mecanica dos outros chats (herda
 * ProspectChat). Usuario de CLIENTE com o pacote Intelligence no contrato; dados sempre os do cliente do usuario
 * logado (lido do usuario a cada acao, nunca do navegador); so consulta.
 */
class IntelligenceChat extends ProspectChat
{
    protected function autorizar(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && ! $user->isSuperAdmin()
            && filled($user->tenant_id)
            && Gate::forUser($user)->allows('viewAny', IntelligenceSnapshot::class),
            403
        );
    }

    protected function servico(): ProspectChatService
    {
        $this->autorizar(); // toda acao reconfere: o estado do componente vem do navegador

        return app(IntelligenceChatService::class)->paraTenant((string) Auth::user()->tenant_id);
    }

    protected function area(): string
    {
        return 'intelligence';
    }

    protected function comRelatorio(): bool
    {
        return true;
    }

    protected function limitePorHora(): ?int
    {
        return 60;
    }

    /** Cota diária de perguntas por cliente (CotaDiariaIa): pequena no teste, maior no plano básico. */
    protected function reservarCota(): ?string
    {
        $cota = app(CotaDiariaIa::class);
        $tenantId = (string) Auth::user()?->tenant_id;
        if (! $cota->aplica($tenantId)) {
            return null;
        }

        return $cota->reservar($tenantId) ? null : $cota->mensagemEsgotada($tenantId);
    }

    protected function devolverCota(): void
    {
        $cota = app(CotaDiariaIa::class);
        $tenantId = (string) Auth::user()?->tenant_id;
        if ($cota->aplica($tenantId)) {
            $cota->devolver($tenantId);
        }
    }

    protected function mensagemErro(\Throwable $e): string
    {
        Log::warning('assistente_intelligence_erro', ['erro' => $e->getMessage()]);

        return 'Não consegui responder agora. Tente de novo em instantes.';
    }

    protected function boasVindas(): string
    {
        return "Olá! Sou o assistente do Intelligence. Respondo sobre a saúde e a evolução da sua empresa, com base nas planilhas que você importou, por exemplo:\n\n• **\"Minha disponibilidade aumentou?\"**\n• **\"Qual área da empresa está pior?\"**\n• **\"Como evoluiu o índice geral nos últimos meses?\"**\n• **\"Como estou em relação a empresas parecidas?\"**\n\nSó consulto, não altero nada. Posso gerar uma lista em Excel ou um relatório para compartilhar no WhatsApp.";
    }

    protected function dadosDaTela(): array
    {
        $cliente = Tenant::find(Auth::user()?->tenant_id)?->name;

        return [
            'tituloApp' => 'Intelligence',
            'subtituloApp' => $cliente ?? 'Saúde e evolução da empresa',
            'placeholder' => 'Pergunte sobre indicadores, áreas, evolução...',
            'mostrarStats' => false,
            'sugestoes' => $this->sugestoesDeSuprimentos() + [
                'Como está minha empresa hoje?' => 'panorama',
                'Minha disponibilidade aumentou?' => 'disponibilidade',
                'Qual área está pior e por quê?' => 'pior-area',
                'O que piorou desde a última fotografia?' => 'piorou',
                'Mostre a evolução do índice geral' => 'evolucao',
                'Como estou contra empresas parecidas?' => 'benchmark',
            ],
            'badge' => 0,
        ];
    }
}
