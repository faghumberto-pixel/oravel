<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaColetaOleoUsado;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPlanoOleo;
use App\Models\FrotaTrocaOleo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Regras do óleo da frota: plano, troca (zera o contador), reposição (não zera) e destinação do óleo usado. */
class OleoService
{
    /**
     * Grava o plano do veículo e desativa o anterior (só um plano ativo por veículo).
     *
     * @param  array{intervalo_km?: ?int, intervalo_dias?: ?int, especificacao_oleo?: ?string, capacidade_litros?: ?int, observacao_filtros?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function salvarPlano(Asset $ativo, array $dados): FrotaPlanoOleo
    {
        $this->exigirVeiculo($ativo);

        if (blank($dados['intervalo_km'] ?? null) && blank($dados['intervalo_dias'] ?? null)) {
            $this->erro('intervalo_km', 'Informe o intervalo em km, em dias, ou nos dois.');
        }

        return DB::transaction(function () use ($ativo, $dados) {
            FrotaPlanoOleo::where('ativo_id', $ativo->id)->where('ativo', true)->update(['ativo' => false]);

            return FrotaPlanoOleo::create(array_merge($dados, ['tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'ativo' => true]));
        });
    }

    /**
     * Registra uma TROCA (zera o contador e calcula a próxima: o que vencer primeiro, km ou dias) ou uma
     * REPOSIÇÃO (não zera). Também gera a leitura de odômetro.
     *
     * @param  array{tipo: string, litros: int|string, produto: string, odometro: int, lote?: ?string, custo?: ?float, peca_id?: ?int, almoxarifado_id?: ?int, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaTrocaOleo
    {
        $this->exigirVeiculo($ativo);

        if (! in_array($dados['tipo'] ?? null, [FrotaTrocaOleo::TROCA, FrotaTrocaOleo::REPOSICAO], true)) {
            $this->erro('tipo', 'Escolha troca ou reposição.');
        }
        $bruto = str_replace(',', '.', trim((string) ($dados['litros'] ?? '0')));
        if (! is_numeric($bruto) || (float) $bruto != floor((float) $bruto)) {
            $this->erro('litros', 'Informe os litros em número inteiro (arredonde para cima).');
        }
        $litros = (int) $bruto;
        if ($litros <= 0 || $litros > 500) {
            $this->erro('litros', 'Informe a quantidade de litros (maior que zero).');
        }
        if (blank(trim((string) ($dados['produto'] ?? '')))) {
            $this->erro('produto', 'Informe o produto (óleo) usado.');
        }

        return DB::transaction(function () use ($ativo, $dados, $litros, $usuario) {
            $troca = $dados['tipo'] === FrotaTrocaOleo::TROCA;
            $plano = $troca ? FrotaPlanoOleo::where('ativo_id', $ativo->id)->where('ativo', true)->first() : null;
            $odometro = (int) $dados['odometro'];

            $registro = FrotaTrocaOleo::create([
                'tenant_id' => $ativo->tenant_id,
                'ativo_id' => $ativo->id,
                'tipo' => $dados['tipo'],
                'odometro' => $odometro,
                'litros' => $litros,
                'produto' => trim($dados['produto']),
                'lote' => $dados['lote'] ?? null,
                'custo' => filled($dados['custo'] ?? null) ? (float) $dados['custo'] : null,
                'realizado_por' => $usuario?->id ?? auth()->id(),
                'realizado_em' => now(),
                'proxima_troca_odometro' => $plano?->intervalo_km ? $odometro + $plano->intervalo_km : null,
                'proxima_troca_data' => $plano?->intervalo_dias ? now()->addDays($plano->intervalo_dias)->toDateString() : null,
                'observacoes' => $dados['observacoes'] ?? null,
                'peca_id' => $dados['peca_id'] ?? null,
                'almoxarifado_id' => $dados['almoxarifado_id'] ?? null,
            ]);

            // Baixa os litros do almoxarifado (volante ou fixo). Saldo insuficiente recusa o registro inteiro.
            app(EstoqueFrotaService::class)->saida($registro, $litros, 'Óleo '.($dados['tipo'] === FrotaTrocaOleo::TROCA ? 'troca' : 'reposição').' — '.($ativo->placa ?: $ativo->name), 'litros');

            // Menor que o odômetro atual: recusa (ValidationException), e nada fica gravado.
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'oleo', $registro->id, null, $usuario?->id);

            return $registro;
        });
    }

    /**
     * Registra a coleta do óleo usado e liga as trocas escolhidas (só TROCAS do mesmo cliente, ainda sem destinação).
     *
     * @param  array{coletada_em: string, litros?: ?int, empresa_coletora: string, numero_documento?: ?string, observacoes?: ?string}  $dados
     * @param  array<int, string>  $trocaIds
     *
     * @throws ValidationException
     */
    public function registrarColeta(array $dados, array $trocaIds, string $tenantId): FrotaColetaOleoUsado
    {
        if (blank(trim((string) ($dados['empresa_coletora'] ?? '')))) {
            $this->erro('empresa_coletora', 'Informe a empresa coletora.');
        }

        return DB::transaction(function () use ($dados, $trocaIds, $tenantId) {
            $trocas = FrotaTrocaOleo::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $trocaIds)->lockForUpdate()->get();

            if ($trocas->count() !== count(array_unique($trocaIds))) {
                $this->erro('trocas', 'Alguma troca escolhida não foi encontrada.');
            }
            if ($trocas->contains(fn (FrotaTrocaOleo $t) => $t->tipo !== FrotaTrocaOleo::TROCA || $t->coleta_oleo_usado_id !== null)) {
                $this->erro('trocas', 'Só trocas de óleo que ainda não têm coleta podem ser ligadas.');
            }

            $litros = filled($dados['litros'] ?? null) ? (int) $dados['litros'] : (int) $trocas->sum('litros');
            if ($litros <= 0) {
                $this->erro('litros', 'Informe os litros coletados.');
            }

            $coleta = FrotaColetaOleoUsado::withoutGlobalScopes()->create(array_merge($dados, ['tenant_id' => $tenantId, 'litros' => $litros]));
            FrotaTrocaOleo::withoutGlobalScopes()->whereIn('id', $trocas->pluck('id'))->update(['coleta_oleo_usado_id' => $coleta->id]);

            return $coleta;
        });
    }

    private function exigirVeiculo(Asset $ativo): void
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'O controle de óleo da frota só vale para ativos do tipo Veículo.');
        }
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
