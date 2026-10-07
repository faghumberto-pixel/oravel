<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaVinculoGps;
use App\Models\User;
use App\Support\TraccarService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GPS (Traccar) alimentando o odômetro: soma a distância percorrida desde a última sincronização ao odômetro do veículo.
 * Só soma o que aconteceu DEPOIS da última leitura manual (checklist, abastecimento...), para não contar duas vezes o trecho
 * que o motorista já informou. A fração que não chega a 1 km fica guardada para a próxima vez.
 */
class GpsOdometroService
{
    public function __construct(private readonly TraccarService $traccar) {}

    /** @throws ValidationException */
    public function vincular(Asset $ativo, string $identificador, ?User $usuario = null): FrotaVinculoGps
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm GPS vinculado aqui.');
        }
        if ($ativo->baixaVigente()) {
            $this->erro('ativo', 'Este veículo foi baixado.');
        }
        $identificador = trim($identificador);
        if ($identificador === '') {
            $this->erro('identificador', 'Informe o identificador (IMEI) do rastreador.');
        }
        if (FrotaVinculoGps::where('ativo_id', $ativo->id)->where('ativo', true)->exists()) {
            $this->erro('ativo', 'Este veículo já tem um GPS vinculado. Desvincule o atual antes.');
        }
        $device = $this->traccar->findDeviceByIdentifier($identificador);
        if (! $device || empty($device['id'])) {
            $this->erro('identificador', 'O Traccar não achou esse identificador. O rastreador precisa já ter enviado uma posição e o servidor precisa estar configurado.');
        }
        if (FrotaVinculoGps::withoutGlobalScopes()->where('traccar_device_id', (int) $device['id'])->where('ativo', true)->exists()) {
            $this->erro('identificador', 'Este rastreador já está vinculado a um veículo.');
        }

        return FrotaVinculoGps::create([
            'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'traccar_device_id' => (int) $device['id'], 'identificador' => $identificador,
            'ultima_sincronizacao' => now(), 'ultimo_resultado' => 'Vinculado; a contagem de km começa agora.', 'registrado_por' => $usuario?->id ?? auth()->id(),
        ]);
    }

    public function desvincular(FrotaVinculoGps $vinculo): FrotaVinculoGps
    {
        $vinculo->update(['ativo' => false]);

        return $vinculo;
    }

    /**
     * Sincroniza um veículo.
     *
     * @return array{status: string, km_adicionados: int, odometro: ?int, mensagem: string} status: aplicado | sem_km | falha | ignorado
     */
    public function sincronizar(FrotaVinculoGps $vinculo, ?Carbon $agora = null): array
    {
        $agora ??= now();
        $ativo = $vinculo->veiculo;
        if (! $vinculo->ativo || ! $ativo || $ativo->baixaVigente()) {
            return $this->resultado($vinculo, 'ignorado', 0, null, 'Vínculo inativo ou veículo baixado.', false);
        }

        // Só conta o trecho depois da última leitura que NÃO veio do GPS (o motorista já informou aquele km).
        $manual = FrotaLeituraOdometro::where('ativo_id', $ativo->id)->where('origem', '!=', 'gps')->latest('lido_em')->first();
        $desde = collect([$vinculo->ultima_sincronizacao, $manual?->lido_em])->filter()->max() ?? $vinculo->created_at;
        if ($desde->gte($agora)) {
            return $this->resultado($vinculo, 'sem_km', 0, (int) floor((float) $ativo->odometro_atual), 'Nada a somar ainda.', true, $agora);
        }

        $km = $this->traccar->distanciaKmOuNulo($vinculo->traccar_device_id, $desde, $agora);
        if ($km === null) {
            return $this->resultado($vinculo, 'falha', 0, null, 'O Traccar não respondeu; tentaremos de novo.', false);
        }
        if ($km > FrotaVinculoGps::LIMITE_KM_POR_SINCRONIZACAO) {
            return $this->resultado($vinculo, 'falha', 0, null, "Distância absurda ({$km} km) ignorada; confira o rastreador.", false);
        }

        $total = (float) $vinculo->km_acumulado + $km;
        $inteiros = (int) floor($total);
        $odometro = (int) floor((float) $ativo->odometro_atual);

        return DB::transaction(function () use ($vinculo, $ativo, $agora, $total, $inteiros, $odometro) {
            if ($inteiros >= 1) {
                $novo = $odometro + $inteiros;
                FrotaLeituraOdometro::registrar($ativo, $novo, 'gps', $vinculo->id);
                $vinculo->update(['km_acumulado' => round($total - $inteiros, 3), 'ultima_sincronizacao' => $agora, 'ultimo_resultado' => "+{$inteiros} km pelo GPS"]);

                return ['status' => 'aplicado', 'km_adicionados' => $inteiros, 'odometro' => $novo, 'mensagem' => "+{$inteiros} km pelo GPS"];
            }
            $vinculo->update(['km_acumulado' => round($total, 3), 'ultima_sincronizacao' => $agora, 'ultimo_resultado' => 'Menos de 1 km desde a última vez']);

            return ['status' => 'sem_km', 'km_adicionados' => 0, 'odometro' => $odometro, 'mensagem' => 'Menos de 1 km desde a última vez'];
        });
    }

    /** Sincroniza todos os vínculos ativos de veículos não baixados. @return array{aplicados: int, sem_km: int, falhas: int, km: int} */
    public function sincronizarTodos(): array
    {
        $r = ['aplicados' => 0, 'sem_km' => 0, 'falhas' => 0, 'km' => 0];
        foreach (FrotaVinculoGps::withoutGlobalScopes()->where('ativo', true)->with('veiculo')->get() as $v) {
            $x = $this->sincronizar($v);
            match ($x['status']) {
                'aplicado' => [$r['aplicados']++, $r['km'] += $x['km_adicionados']],
                'sem_km' => $r['sem_km']++,
                'falha' => $r['falhas']++,
                default => null,
            };
        }

        return $r;
    }

    /** Vínculo ativo atrasado (sem sincronizar há mais de ATRASO_HORAS): horas de atraso, ou null se está em dia. */
    public static function horasSemSincronizar(FrotaVinculoGps $v): ?int
    {
        $ref = $v->ultima_sincronizacao ?? $v->created_at;
        $horas = intdiv(max(0, now()->timestamp - $ref->timestamp), 3600);

        return $horas > FrotaVinculoGps::ATRASO_HORAS ? $horas : null;
    }

    /** @return array{status: string, km_adicionados: int, odometro: ?int, mensagem: string} */
    private function resultado(FrotaVinculoGps $v, string $status, int $km, ?int $odometro, string $mensagem, bool $avancar, ?Carbon $agora = null): array
    {
        $v->update(['ultimo_resultado' => $mensagem] + ($avancar && $agora ? ['ultima_sincronizacao' => $agora] : []));

        return ['status' => $status, 'km_adicionados' => $km, 'odometro' => $odometro, 'mensagem' => $mensagem];
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
