<?php

namespace App\Integracoes\Observatorio;

use App\Jobs\EnviarEventoObservatorio;
use App\Models\ObservatorioEnvio;
use App\Models\Prospect;
use App\Models\ProspectContato;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registra uma entrevista como ATIVIDADE do CRM (prospect_contatos, sem respostas) e enfileira o evento
 * para o Observatorio na mesma transacao (outbox). Nenhuma estatistica nem armazenamento de respostas aqui.
 */
class RegistrarEntrevista
{
    public const CANAIS_CONTRATO = ['telefone', 'whatsapp', 'site', 'evento', 'email'];

    private const CANAL_CRM = ['telefone' => 'telefone', 'whatsapp' => 'whatsapp', 'email' => 'email', 'site' => 'outro', 'evento' => 'outro'];

    /**
     * @param  array  $pesquisa  definicao vinda de pesquisasAtivas(): id, versao, titulo, perguntas
     * @param  array<int|string, mixed>  $respostas  pergunta_id => valor (string) ou lista (multipla)
     * @param  array{canal?:string, uf?:?string, contato_nome?:?string, observacao_livre?:?string}  $dados
     */
    public function executar(Prospect $prospect, array $pesquisa, array $dados, array $respostas, ?string $userId = null): ObservatorioEnvio
    {
        $canal = in_array($dados['canal'] ?? 'telefone', self::CANAIS_CONTRATO, true) ? ($dados['canal'] ?? 'telefone') : 'telefone';
        $uf = isset($dados['uf']) && $dados['uf'] !== '' ? strtoupper($dados['uf']) : null;
        $agora = now();

        $envelope = [
            'event_id' => (string) Str::uuid(), 'event_type' => 'entrevista.registrada', 'schema_version' => '1',
            'occurred_at' => $agora->toIso8601String(), 'source' => 'central',
            'data' => [
                'entrevista_ref' => (string) Str::uuid(), 'pesquisa_id' => (int) $pesquisa['id'], 'pesquisa_versao' => (int) $pesquisa['versao'],
                'empresa_ref' => EmpresaRef::de($prospect->id),
                'segmentacao' => ['porte' => $prospect->porte, 'uf' => $uf, 'segmento' => $prospect->atividade],
                'canal' => $canal, 'registrado_em' => $agora->toIso8601String(),
                'respostas' => $this->respostas($respostas),
                'observacao_livre' => filled($dados['observacao_livre'] ?? null) ? $dados['observacao_livre'] : null,
            ],
        ];

        return DB::transaction(function () use ($prospect, $pesquisa, $envelope, $canal, $dados, $userId, $agora) {
            ProspectContato::create([
                'prospect_id' => $prospect->id, 'data' => $agora->toDateString(), 'canal' => self::CANAL_CRM[$canal],
                'titulo' => 'Entrevista do Observatório: '.$pesquisa['titulo'],
                // So' o fato da entrevista. Respostas e observacoes vao ao Observatorio e nao ficam aqui.
                'texto' => 'Entrevista registrada e enviada ao Observatório.'.(filled($dados['contato_nome'] ?? null) ? ' Contato: '.$dados['contato_nome'].'.' : ''),
                'autor' => $userId ? optional(User::find($userId))->name : null,
            ]);
            $prospect->forceFill(['ultimo_contato_em' => $agora])->save();

            $envio = ObservatorioEnvio::create([
                'id' => $envelope['event_id'], 'entrevista_ref' => $envelope['data']['entrevista_ref'], 'prospect_id' => $prospect->id,
                'pesquisa_id' => $pesquisa['id'], 'pesquisa_titulo' => $pesquisa['titulo'], 'payload' => $envelope,
                'status' => 'pendente', 'proxima_tentativa_em' => $agora, 'user_id' => $userId,
            ]);
            EnviarEventoObservatorio::dispatch($envio->id)->afterCommit();

            return $envio;
        });
    }

    /** @return list<array{pergunta_id:int, valor?:string, valores?:list<string>}> */
    private function respostas(array $respostas): array
    {
        $saida = [];
        foreach ($respostas as $perguntaId => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $saida[] = is_array($v)
                ? ['pergunta_id' => (int) $perguntaId, 'valores' => array_values(array_map('strval', $v))]
                : ['pergunta_id' => (int) $perguntaId, 'valor' => (string) $v];
        }

        return $saida;
    }
}
