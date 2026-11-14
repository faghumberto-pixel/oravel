<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\Prospect;

/**
 * Analise de um Prospect via Claude API -- sugere proximos passos,
 * identifica riscos, gera mensagens de follow-up e prioriza acoes.
 *
 * Padrao igual ao CommercialLeadAnalysisService: usa AnthropicApiClient
 * compartilhado, degrada graciosamente sem API key ou com falha de API.
 * Salva o registro em AIAnalysis para auditoria e historico.
 */
class ProspectAnalysisService
{
    public function __construct(private AnthropicApiClient $client) {}

    public function analyze(Prospect $prospect, string $userId): AIAnalysis
    {
        $context = $this->buildContext($prospect);

        $analysis = AIAnalysis::create([
            'tenant_id' => null, // Prospects sao globais (sem tenant)
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_PROSPECT,
            'prospect_id' => $prospect->id,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        $result = $this->client->send(
            $this->systemPrompt(),
            "Dados do prospect:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        if (! $result['ok']) {
            $analysis->update(['status' => AIAnalysis::STATUS_FALHOU, 'error' => $result['error']]);

            return $analysis;
        }

        $parsed = $this->client->parseJson($result['text']);

        if ($parsed === null) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => 'A resposta da IA não veio em um formato reconhecível.',
                'response' => ['raw' => $result['text']],
            ]);

            return $analysis;
        }

        $analysis->update(['status' => AIAnalysis::STATUS_CONCLUIDA, 'response' => $parsed]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um consultor de vendas B2B especializado em prospecção ativa para
            locação e manutenção de equipamentos pesados. O usuário (super admin da
            Oravel) está abordando empresas que ainda NÃO são clientes.

            Analise o prospect recebido (situação atual, histórico de contatos,
            dados da empresa, próxima ação agendada) e entregue um plano de ação
            tático e direto.

            Responda APENAS com um objeto JSON válido, sem markdown, sem crases,
            sem texto antes ou depois, no formato exato:

            {
              "prioridade": "alta|media|baixa",
              "risco_perda": "string (ex: 'Alto — 15 dias sem resposta após envio')",
              "proximos_passos": [
                {"acao": "string", "prazo_dias": int, "canal": "whatsapp|telefone|email|visita", "roteiro": "string"}
              ],
              "mensagem_sugerida": "string (pronta para copiar/colar no WhatsApp/email)",
              "pontos_atencao": ["string", "string"],
              "qualificacao": "quente|morno|frio|desqualificado"
            }

            Diretrizes:
            - prioridade: "alta" se estiver em 'em_teste' ou 'reuniao_agendada' ou 'proposta_enviada'
            - prioridade: "media" se em 'enviado' ou 'respondeu' com follow-up vencido
            - prioridade: "baixa" se 'nao_enviado' ou 'sem_interesse' (mas ainda vale reativar)
            - proximos_passos: máximo 3 itens, ordenados por urgência
            - mensagem_sugerida: tom humano, direto, com call-to-action claro
            - pontos_atencao: sinais de alerta (ex: concorrente, budget, timing)
            - qualificacao: baseie-se em nota, status, interações, porte da empresa
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Prospect $prospect): array
    {
        $ultimoContato = $prospect->contatos()->latest('data')->first();
        $proximoContato = $prospect->proximo_contato;
        $diasSemContato = $ultimoContato ? (int) now()->diffInDays($ultimoContato->data) : null;
        $diasProximoContato = $proximoContato ? (int) now()->diffInDays($proximoContato, false) : null;
        $followUpVencido = $proximoContato && $proximoContato->lte(now());

        $contatosRecentes = $prospect->contatos()->latest('data')->take(5)->get()->map(function ($c) {
            return [
                'data' => $c->data?->format('d/m/Y'),
                'canal' => $c->canal,
                'titulo' => $c->titulo,
                'resumo' => $c->texto,
                'autor' => $c->autor,
            ];
        })->values()->all();

        return [
            'prospect' => [
                'nome' => $prospect->nome,
                'razao_social' => $prospect->razao_social,
                'cnpj' => $prospect->cnpj,
                'cidade' => $prospect->cidade,
                'atividade' => $prospect->atividade,
                'porte' => $prospect->porte,
                'capital' => $prospect->capital,
                'ano_abertura' => $prospect->ano_abertura,
                'nota' => $prospect->nota,
                'regiao_campinas' => $prospect->regiao_campinas,
                'empresa_nova' => $prospect->empresa_nova,
                'status_atual' => Prospect::STATUS[$prospect->status] ?? $prospect->status,
                'resultado_atual' => $prospect->resultado ? (Prospect::RESULTADOS[$prospect->resultado] ?? $prospect->resultado) : null,
                'lote' => $prospect->lote,
                'origem' => $prospect->origem,
            ],
            'contato_principal' => [
                'socio' => $prospect->socio,
                'socio_cargo' => $prospect->socio_cargo,
                'telefone' => $prospect->telefone,
                'email' => $prospect->email,
                'eh_celular' => $prospect->ehCelular(),
            ],
            'ultima_interacao' => $ultimoContato ? [
                'data' => $ultimoContato->data?->format('d/m/Y'),
                'dias_atras' => $diasSemContato,
                'canal' => $ultimoContato->canal,
                'titulo' => $ultimoContato->titulo,
                'resumo' => $ultimoContato->texto,
            ] : null,
            'historico_recente' => $contatosRecentes,
            'proximo_contato_agendado' => $proximoContato ? [
                'data' => $proximoContato->format('d/m/Y'),
                'dias_para_frente' => $diasProximoContato,
                'vencido' => $followUpVencido,
            ] : null,
            'observacoes' => $prospect->observacoes,
            'o_que_olhar' => $prospect->o_que_olhar,
            'mensagem_personalizada' => $prospect->mensagem_personalizada,
        ];
    }
}