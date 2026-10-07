<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPlanoRevisao;
use App\Models\FrotaRevisaoRealizada;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Revisões preventivas por km/tempo: planos por veículo, realização (zera o contador do item) e situação calculada ao vivo. */
class RevisaoService
{
    public const SEM_REGISTRO = 'sem_registro';

    public const EM_DIA = 'em_dia';

    public const PROXIMA = 'proxima';

    public const VENCIDA = 'vencida';

    /** Sugestões editáveis (não são regra do fabricante): nome, km, dias. */
    public const PADRAO = [
        ['Freios (pastilhas/lonas)', 30000, 365], ['Filtro de ar', 20000, 365], ['Filtro de combustível', 20000, 365],
        ['Correia', 60000, 1095], ['Alinhamento e balanceamento', 10000, 180], ['Fluido de freio', 40000, 730],
    ];

    /**
     * @param  array{nome: string, intervalo_km?: ?int, intervalo_dias?: ?int, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function criarPlano(Asset $ativo, array $dados): FrotaPlanoRevisao
    {
        $this->exigirVeiculo($ativo);
        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome === '') {
            $this->erro('nome', 'Informe o item da revisão (ex.: Freios).');
        }
        if (blank($dados['intervalo_km'] ?? null) && blank($dados['intervalo_dias'] ?? null)) {
            $this->erro('intervalo_km', 'Informe o intervalo em km, em dias, ou nos dois.');
        }
        if (FrotaPlanoRevisao::where('ativo_id', $ativo->id)->where('ativo', true)->whereRaw('lower(nome) = ?', [mb_strtolower($nome)])->exists()) {
            $this->erro('nome', 'Este veículo já tem um plano ativo para "'.$nome.'".');
        }

        return FrotaPlanoRevisao::create(['tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'nome' => $nome,
            'intervalo_km' => ($dados['intervalo_km'] ?? null) ?: null, 'intervalo_dias' => ($dados['intervalo_dias'] ?? null) ?: null, 'observacoes' => $dados['observacoes'] ?? null]);
    }

    /** Cria os itens sugeridos que o veículo ainda não tem. @return int quantos foram criados */
    public function aplicarPadrao(Asset $ativo): int
    {
        $this->exigirVeiculo($ativo);
        $criados = 0;
        foreach (self::PADRAO as [$nome, $km, $dias]) {
            if (! FrotaPlanoRevisao::where('ativo_id', $ativo->id)->where('ativo', true)->whereRaw('lower(nome) = ?', [mb_strtolower($nome)])->exists()) {
                $this->criarPlano($ativo, ['nome' => $nome, 'intervalo_km' => $km, 'intervalo_dias' => $dias]);
                $criados++;
            }
        }

        return $criados;
    }

    /**
     * @param  array{realizada_em?: ?string, odometro: int|string, custo?: float|int|string|null, ordem_servico_id?: ?string, observacoes?: ?string, justificativa_odometro?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(FrotaPlanoRevisao $plano, array $dados, ?User $usuario = null): FrotaRevisaoRealizada
    {
        if (! $plano->ativo) {
            $this->erro('plano', 'Este plano está desativado.');
        }
        $dia = blank($dados['realizada_em'] ?? null) ? now()->startOfDay() : Carbon::parse($dados['realizada_em'])->startOfDay();
        if ($dia->gt(now()->endOfDay())) {
            $this->erro('realizada_em', 'A data da revisão não pode estar no futuro.');
        }
        $custo = filled($dados['custo'] ?? null) ? (float) str_replace(',', '.', (string) $dados['custo']) : null;
        if ($custo !== null && $custo < 0) {
            $this->erro('custo', 'O custo não pode ser negativo.');
        }

        return DB::transaction(function () use ($plano, $dados, $usuario, $dia, $custo) {
            $odometro = (int) $dados['odometro'];
            $registro = FrotaRevisaoRealizada::create([
                'tenant_id' => $plano->tenant_id, 'plano_id' => $plano->id, 'ativo_id' => $plano->ativo_id, 'realizada_em' => $dia, 'odometro' => $odometro,
                'custo' => $custo, 'ordem_servico_id' => $dados['ordem_servico_id'] ?? null, 'observacoes' => $dados['observacoes'] ?? null,
                'realizado_por' => $usuario?->id ?? auth()->id(),
            ]);
            // Km menor que o último só com justificativa (a recusa desfaz tudo).
            FrotaLeituraOdometro::registrar($plano->veiculo, $odometro, 'revisao', $registro->id, $dados['justificativa_odometro'] ?? null, $usuario?->id);

            return $registro;
        });
    }

    /** @throws ValidationException */
    public function desativar(FrotaPlanoRevisao $plano): FrotaPlanoRevisao
    {
        $plano->update(['ativo' => false]);

        return $plano;
    }

    /**
     * Situação do item, calculada ao vivo: vence pelo que ocorrer primeiro (km ou dias) desde a última realização.
     *
     * @return array{situacao: string, mensagem: string, proxima_km: ?int, proxima_data: ?string, km_restantes: ?int, dias_restantes: ?int}
     */
    public static function situacao(FrotaPlanoRevisao $plano): array
    {
        $base = ['situacao' => self::SEM_REGISTRO, 'mensagem' => $plano->nome.': nenhuma revisão registrada ainda', 'proxima_km' => null, 'proxima_data' => null, 'km_restantes' => null, 'dias_restantes' => null];
        $ultima = $plano->ultima();
        if (! $ultima) {
            return $base;
        }
        $atual = (int) floor((float) $plano->veiculo?->odometro_atual);
        $proximaKm = $plano->intervalo_km ? $ultima->odometro + $plano->intervalo_km : null;
        $proximaData = $plano->intervalo_dias ? $ultima->realizada_em->copy()->addDays($plano->intervalo_dias) : null;
        $km = $proximaKm !== null ? $proximaKm - $atual : null;
        $dias = $proximaData ? (int) now()->startOfDay()->diffInDays($proximaData->copy()->startOfDay(), false) : null;

        $situacao = match (true) {
            ($km !== null && $km <= 0) || ($dias !== null && $dias <= 0) => self::VENCIDA,
            ($km !== null && $km <= FrotaPlanoRevisao::AVISO_KM) || ($dias !== null && $dias <= FrotaPlanoRevisao::AVISO_DIAS) => self::PROXIMA,
            default => self::EM_DIA,
        };
        $partes = array_filter([
            $km !== null ? ($km <= 0 ? 'passou '.number_format(abs($km), 0, ',', '.').' km' : 'faltam '.number_format($km, 0, ',', '.').' km') : null,
            $dias !== null ? ($dias <= 0 ? 'venceu há '.abs($dias).' dia(s)' : "faltam {$dias} dia(s)") : null,
        ]);

        return [
            'situacao' => $situacao,
            'mensagem' => $plano->nome.': '.['vencida' => 'revisão VENCIDA', 'proxima' => 'revisão próxima', 'em_dia' => 'em dia'][$situacao].' ('.implode(' · ', $partes).')',
            'proxima_km' => $proximaKm, 'proxima_data' => $proximaData?->toDateString(), 'km_restantes' => $km, 'dias_restantes' => $dias,
        ];
    }

    private function exigirVeiculo(Asset $ativo): void
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'As revisões da frota só valem para ativos do tipo Veículo.');
        }
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
