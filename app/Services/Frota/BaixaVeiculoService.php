<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaBaixa;
use App\Models\FrotaChave;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaSaidaVeiculo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Baixa do veículo (venda, sucata, perda total, doação): encerra o titular, desativa as chaves e tira o veículo das listas e das pendências. */
class BaixaVeiculoService
{
    /**
     * @param  array{tipo: string, data?: ?string, motivo: string, valor?: float|int|string|null, comprador?: ?string, documento?: ?string, odometro_final?: int|string|null, justificativa_odometro?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaBaixa
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm baixa registrada aqui.');
        }
        if (! array_key_exists($dados['tipo'] ?? '', FrotaBaixa::tipoLabels())) {
            $this->erro('tipo', 'Escolha o tipo da baixa.');
        }
        if (blank(trim((string) ($dados['motivo'] ?? '')))) {
            $this->erro('motivo', 'Informe o motivo da baixa.');
        }
        if ($ativo->baixaVigente()) {
            $this->erro('ativo', 'Este veículo já está baixado.');
        }
        if ($fora = FrotaSaidaVeiculo::fora()->where('ativo_id', $ativo->id)->first()) {
            $this->erro('ativo', 'O veículo está fora desde '.$fora->saida_em->format('d/m H:i').'. Registre a entrada antes da baixa.');
        }
        $valor = filled($dados['valor'] ?? null) ? (float) str_replace(',', '.', (string) $dados['valor']) : null;
        if ($dados['tipo'] === 'venda' && ($valor === null || $valor <= 0)) {
            $this->erro('valor', 'Informe o valor da venda.');
        }
        if ($valor !== null && $valor < 0) {
            $this->erro('valor', 'O valor não pode ser negativo.');
        }
        $dia = blank($dados['data'] ?? null) ? now()->startOfDay() : Carbon::parse($dados['data'])->startOfDay();
        if ($dia->gt(now()->endOfDay())) {
            $this->erro('data', 'A data da baixa não pode estar no futuro.');
        }
        $chaveFora = FrotaChave::where('ativo_id', $ativo->id)->where('ativo', true)->get()->first(fn (FrotaChave $c) => $c->entregaAberta() !== null);
        if ($chaveFora) {
            $this->erro('ativo', 'A chave "'.$chaveFora->identificacao.'" está fora. Registre a devolução antes da baixa.');
        }

        return DB::transaction(function () use ($ativo, $dados, $usuario, $valor, $dia) {
            $baixa = FrotaBaixa::create([
                'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'tipo' => $dados['tipo'], 'data' => $dia, 'valor' => $valor,
                'comprador' => $dados['comprador'] ?? null, 'documento' => $dados['documento'] ?? null,
                'odometro_final' => filled($dados['odometro_final'] ?? null) ? (int) $dados['odometro_final'] : null,
                'motivo' => trim($dados['motivo']), 'registrado_por' => $usuario?->id ?? auth()->id(),
            ]);
            if ($baixa->odometro_final !== null) {
                FrotaLeituraOdometro::registrar($ativo, $baixa->odometro_final, 'baixa', $baixa->id, $dados['justificativa_odometro'] ?? null, $usuario?->id);
            }
            if ($titular = VinculoMotoristaService::titular($ativo)) {
                $titular->update(['fim' => max($dia, $titular->inicio)]);
            }
            FrotaChave::where('ativo_id', $ativo->id)->update(['ativo' => false]);

            return $baixa;
        });
    }

    /** @throws ValidationException */
    public function reverter(FrotaBaixa $baixa, string $motivo): FrotaBaixa
    {
        if (! $baixa->vigente()) {
            $this->erro('baixa', 'Esta baixa já foi revertida.');
        }
        if (blank(trim($motivo))) {
            $this->erro('motivo', 'Informe o motivo da reversão.');
        }
        $baixa->update(['revertida_em' => now(), 'motivo_reversao' => trim($motivo)]);

        return $baixa;
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
