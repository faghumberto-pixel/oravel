<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaLeituraOdometro;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Abastecimentos: registro, consumo pelo método do tanque cheio, desvio em relação ao histórico e resumo por período. */
class AbastecimentoService
{
    /**
     * @param  array<string, mixed>  $dados  abastecido_em, odometro, combustivel, litros, (valor_litro e/ou valor_total), tanque_cheio, origem, posto, nota_fiscal, motorista_id, observacoes, peca_id, almoxarifado_id, justificativa_odometro
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaAbastecimento
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm abastecimento registrado aqui.');
        }
        if (! array_key_exists($dados['combustivel'] ?? '', FrotaAbastecimento::combustivelLabels())) {
            $this->erro('combustivel', 'Escolha o combustível.');
        }
        $origem = $dados['origem'] ?? 'externo';
        if (! array_key_exists($origem, FrotaAbastecimento::origemLabels())) {
            $this->erro('origem', 'Escolha a origem do abastecimento.');
        }
        $litros = $this->numero($dados['litros'] ?? 0);
        if ($litros <= 0 || $litros > 2000) {
            $this->erro('litros', 'Informe os litros abastecidos (maior que zero).');
        }
        [$valorLitro, $valorTotal] = $this->valores($litros, $dados);
        $quando = blank($dados['abastecido_em'] ?? null) ? now() : Carbon::parse($dados['abastecido_em']);
        if ($quando->gt(now()->addMinutes(5))) {
            $this->erro('abastecido_em', 'A data do abastecimento não pode estar no futuro.');
        }
        if (filled($dados['motorista_id'] ?? null) && ! FleetDriver::find($dados['motorista_id'])) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }

        return DB::transaction(function () use ($ativo, $dados, $usuario, $origem, $litros, $valorLitro, $valorTotal, $quando) {
            $ultimo = FrotaAbastecimento::where('ativo_id', $ativo->id)->latest('abastecido_em')->lockForUpdate()->first();
            if ($ultimo && $quando->lt($ultimo->abastecido_em)) {
                $this->erro('abastecido_em', 'Há um abastecimento mais recente ('.$ultimo->abastecido_em->format('d/m/Y H:i').'). Registre em ordem cronológica.');
            }

            $odometro = (int) $dados['odometro'];
            $cheio = (bool) ($dados['tanque_cheio'] ?? true);
            [$km, $consumo] = $cheio ? $this->consumo($ativo, $odometro, $litros) : [null, null];

            $registro = FrotaAbastecimento::create([
                'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'motorista_id' => $dados['motorista_id'] ?? null,
                'abastecido_em' => $quando, 'odometro' => $odometro, 'combustivel' => $dados['combustivel'], 'litros' => $litros,
                'valor_litro' => $valorLitro, 'valor_total' => $valorTotal, 'tanque_cheio' => $cheio, 'origem' => $origem,
                'posto' => $dados['posto'] ?? null, 'nota_fiscal' => $dados['nota_fiscal'] ?? null, 'km_rodado' => $km, 'consumo_km_l' => $consumo,
                'observacoes' => $dados['observacoes'] ?? null, 'registrado_por' => $usuario?->id ?? auth()->id(),
                'peca_id' => $origem === 'tanque_proprio' ? ($dados['peca_id'] ?? null) : null,
                'almoxarifado_id' => $origem === 'tanque_proprio' ? ($dados['almoxarifado_id'] ?? null) : null,
            ]);

            // Km menor que o último só com justificativa (recusa desfaz tudo); depois baixa o combustível do tanque próprio.
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'abastecimento', $registro->id, $dados['justificativa_odometro'] ?? null, $usuario?->id);
            app(EstoqueFrotaService::class)->saida($registro, $litros, 'Abastecimento — '.($ativo->placa ?: $ativo->name), 'litros');

            return $registro;
        });
    }

    /**
     * Tanque cheio: km desde o último tanque cheio ÷ litros abastecidos desde então (inclui os parciais no meio, exclui o do próprio anterior).
     *
     * @return array{0: ?int, 1: ?float}
     */
    private function consumo(Asset $ativo, int $odometro, float $litros): array
    {
        $ancora = FrotaAbastecimento::where('ativo_id', $ativo->id)->where('tanque_cheio', true)->latest('abastecido_em')->first();
        if (! $ancora) {
            return [null, null];
        }
        $km = $odometro - $ancora->odometro;
        $total = $litros + (float) FrotaAbastecimento::where('ativo_id', $ativo->id)->where('abastecido_em', '>', $ancora->abastecido_em)->sum('litros');

        return $km > 0 && $total > 0 ? [$km, round($km / $total, 2)] : [null, null];
    }

    /**
     * Situação do consumo de um abastecimento em relação à média dos consumos anteriores do veículo.
     *
     * @return array{situacao: string, media: ?float, razao: ?float} situacao: sem_base | normal | atencao | critica
     */
    public static function desvio(FrotaAbastecimento $a): array
    {
        $vazio = ['situacao' => 'sem_base', 'media' => null, 'razao' => null];
        if ($a->consumo_km_l === null) {
            return $vazio;
        }
        $anteriores = FrotaAbastecimento::where('ativo_id', $a->ativo_id)->whereNotNull('consumo_km_l')->where('abastecido_em', '<', $a->abastecido_em)
            ->latest('abastecido_em')->limit(FrotaAbastecimento::JANELA_REFERENCIA)->pluck('consumo_km_l');
        if ($anteriores->count() < FrotaAbastecimento::MINIMO_REFERENCIA) {
            return $vazio;
        }
        $media = (float) $anteriores->avg();
        $razao = $media > 0 ? (float) $a->consumo_km_l / $media : null;
        $situacao = match (true) {
            $razao === null => 'sem_base',
            $razao < FrotaAbastecimento::DESVIO_CRITICO => 'critica',
            $razao < FrotaAbastecimento::DESVIO_ATENCAO => 'atencao',
            default => 'normal',
        };

        return ['situacao' => $situacao, 'media' => round($media, 2), 'razao' => $razao !== null ? round($razao, 3) : null];
    }

    /**
     * Resumo do veículo nos últimos N meses: litros, gasto, km rodado, km/l médio e custo por km (só trechos de tanque cheio).
     *
     * @return array{abastecimentos: int, litros: float, gasto: float, km: int, km_l: ?float, custo_km: ?float}
     */
    public static function resumo(Asset $ativo, int $meses = 3): array
    {
        $lista = FrotaAbastecimento::where('ativo_id', $ativo->id)->where('abastecido_em', '>=', now()->subMonths($meses))->get();
        $comConsumo = $lista->whereNotNull('consumo_km_l');
        $km = (int) $comConsumo->sum('km_rodado');
        $litrosTrecho = (float) $comConsumo->sum(fn (FrotaAbastecimento $a) => $a->km_rodado / (float) $a->consumo_km_l);
        $gastoTrecho = (float) $comConsumo->sum(fn (FrotaAbastecimento $a) => (float) $a->valor_total);

        return [
            'abastecimentos' => $lista->count(),
            'litros' => round((float) $lista->sum('litros'), 2),
            'gasto' => round((float) $lista->sum('valor_total'), 2),
            'km' => $km,
            'km_l' => $litrosTrecho > 0 ? round($km / $litrosTrecho, 2) : null,
            'custo_km' => $km > 0 ? round($gastoTrecho / $km, 2) : null,
        ];
    }

    /** @return array{0: float, 1: float} valor_litro, valor_total */
    private function valores(float $litros, array $dados): array
    {
        $litro = $this->numero($dados['valor_litro'] ?? 0);
        $total = $this->numero($dados['valor_total'] ?? 0);
        if ($total <= 0 && $litro <= 0) {
            $this->erro('valor_total', 'Informe o valor total ou o valor por litro.');
        }
        if ($total <= 0) {
            $total = round($litro * $litros, 2);
        } elseif ($litro <= 0) {
            $litro = round($total / $litros, 3);
        }

        return [$litro, $total];
    }

    private function numero(mixed $valor): float
    {
        return (float) str_replace(',', '.', trim((string) $valor));
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
