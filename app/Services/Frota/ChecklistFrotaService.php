<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaChecklist;
use App\Models\FrotaInspecaoPneu;
use App\Models\FrotaItemModeloChecklist as Item;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaRespostaChecklist as Resposta;
use App\Models\FrotaTesteBateria;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Regras do checklist de saída e retorno da frota: situação, bloqueio, liberação, odômetro e pareamento. */
class ChecklistFrotaService
{
    /**
     * Situação pelas respostas: problema em item crítico = bloqueado; em item de atenção = atenção; senão ok.
     *
     * @param  iterable<array{gravidade: string, resultado: string}>  $respostas
     */
    public static function situacaoDas(iterable $respostas): string
    {
        $situacao = FrotaChecklist::OK;

        foreach ($respostas as $r) {
            if (($r['resultado'] ?? null) !== Resposta::PROBLEMA) {
                continue;
            }
            if (($r['gravidade'] ?? null) === Item::GRAVIDADE_CRITICA) {
                return FrotaChecklist::BLOQUEADO;
            }
            if (($r['gravidade'] ?? null) === Item::GRAVIDADE_ATENCAO) {
                $situacao = FrotaChecklist::ATENCAO;
            }
        }

        return $situacao;
    }

    /**
     * Registra um checklist.
     *
     * @param  array{tipo: string, modelo_id: string, motorista_id?: ?string, odometro: int, nivel_combustivel?: ?string, assinatura?: ?string, observacoes?: ?string, justificativa_odometro?: ?string, movimentacao_equipamento_id?: ?string, respostas: array<string, array{resultado: string, valor?: mixed, observacao?: ?string}>}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaChecklist
    {
        if (! $ativo->isVehicle()) {
            throw ValidationException::withMessages(['ativo' => 'O checklist da frota só vale para ativos do tipo Veículo.']);
        }

        $modelo = FrotaModeloChecklist::with('itens')->findOrFail($dados['modelo_id']);

        // Cada item do modelo precisa de resposta.
        $respostasLinhas = [];
        foreach ($modelo->itens as $item) {
            $r = $dados['respostas'][$item->id] ?? null;
            if (! $r || ! in_array($r['resultado'] ?? null, [Resposta::OK, Resposta::PROBLEMA, Resposta::NAO_SE_APLICA], true)) {
                throw ValidationException::withMessages(['respostas' => "Responda o item: {$item->descricao}."]);
            }
            $respostasLinhas[] = [
                'item_id' => $item->id,
                'descricao_registrada' => $item->descricao,
                'categoria_registrada' => $item->categoria,
                'gravidade_registrada' => $item->gravidade,
                'resultado' => $r['resultado'],
                'valor_numerico' => filled($r['valor'] ?? null) ? (float) str_replace(',', '.', (string) $r['valor']) : null,
                'unidade' => $item->unidade,
                'observacao' => $r['observacao'] ?? null,
            ];
        }

        $situacao = self::situacaoDas(array_map(fn ($l) => ['gravidade' => $l['gravidade_registrada'], 'resultado' => $l['resultado']], $respostasLinhas));

        return DB::transaction(function () use ($ativo, $dados, $usuario, $respostasLinhas, $situacao) {
            $usuario ??= auth()->user();

            // Odômetro: menor que o anterior só com justificativa (lança ValidationException).
            $checklist = FrotaChecklist::create([
                'tenant_id' => $ativo->tenant_id,
                'ativo_id' => $ativo->id,
                'motorista_id' => $dados['motorista_id'] ?? null,
                'modelo_id' => $dados['modelo_id'],
                'preenchido_por' => $usuario?->id,
                'tipo' => $dados['tipo'],
                'odometro' => (int) $dados['odometro'],
                'nivel_combustivel' => $dados['nivel_combustivel'] ?? null,
                'situacao' => $situacao,
                'assinatura' => $dados['assinatura'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'concluido_em' => now(),
                'movimentacao_equipamento_id' => $dados['movimentacao_equipamento_id'] ?? null,
                'checklist_par_id' => $dados['tipo'] === FrotaChecklist::TIPO_RETORNO ? $this->saidaParaPar($ativo)?->id : null,
            ]);

            FrotaLeituraOdometro::registrar($ativo, (int) $dados['odometro'], 'checklist', $checklist->id, $dados['justificativa_odometro'] ?? null, $usuario?->id);

            foreach ($respostasLinhas as $linha) {
                $checklist->respostas()->create($linha);
            }

            // Tensão medida no checklist completo vira teste de bateria: se o veículo tem UMA bateria montada, é dela;
            // com 0 ou 2+ não dá para saber qual, então fica no veículo (bateria_id nulo).
            foreach ($respostasLinhas as $linha) {
                if (($linha['unidade'] ?? null) === 'V' && $linha['valor_numerico'] !== null && $linha['resultado'] !== Resposta::NAO_SE_APLICA) {
                    $montadas = $ativo->bateriasMontadas()->pluck('componente_id');
                    FrotaTesteBateria::create([
                        'bateria_id' => $montadas->count() === 1 ? $montadas->first() : null,
                        'ativo_id' => $ativo->id,
                        'checklist_id' => $checklist->id,
                        'tensao' => $linha['valor_numerico'],
                        'odometro' => (int) $dados['odometro'],
                        'testado_em' => now(),
                    ]);
                }
            }

            // Sulco medido no checklist completo vira inspeção de pneu do VEÍCULO (menor sulco medido; sem pneu específico).
            foreach ($respostasLinhas as $linha) {
                if (($linha['categoria_registrada'] ?? null) === 'pneus' && ($linha['unidade'] ?? null) === 'mm' && $linha['valor_numerico'] !== null && $linha['resultado'] !== Resposta::NAO_SE_APLICA) {
                    FrotaInspecaoPneu::create([
                        'pneu_id' => null,
                        'ativo_id' => $ativo->id,
                        'checklist_id' => $checklist->id,
                        'sulco_mm' => $linha['valor_numerico'],
                        'odometro' => (int) $dados['odometro'],
                        'inspecionado_em' => now(),
                        'observacao' => 'Menor sulco medido no checklist',
                    ]);
                }
            }

            return $checklist;
        });
    }

    /** A saída (concluída, não bloqueada sem liberação) que ainda não tem retorno: é o par do próximo retorno. */
    private function saidaParaPar(Asset $ativo): ?FrotaChecklist
    {
        $saida = $ativo->ultimaSaidaFrota();

        if (! $saida || $saida->estaBloqueado()) {
            return null;
        }

        $jaTemRetorno = FrotaChecklist::where('checklist_par_id', $saida->id)->exists();

        return $jaTemRetorno ? null : $saida;
    }

    /**
     * Libera um veículo bloqueado pelo checklist, com motivo obrigatório. Só quem tem a permissão
     * liberar_checklist_frota (ou é administrador do cliente).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function liberar(FrotaChecklist $checklist, User $usuario, string $motivo): FrotaChecklist
    {
        if (! Gate::forUser($usuario)->allows('liberar', $checklist)) {
            throw new AuthorizationException('Você não tem permissão para liberar veículos bloqueados.');
        }

        if (! $checklist->estaBloqueado()) {
            throw ValidationException::withMessages(['situacao' => 'Este checklist não está bloqueado.']);
        }

        if (blank(trim($motivo))) {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo da liberação.']);
        }

        $checklist->update([
            'situacao' => FrotaChecklist::LIBERADO,
            'liberado_por' => $usuario->id,
            'liberado_em' => now(),
            'motivo_liberacao' => trim($motivo),
        ]);

        return $checklist;
    }
}
