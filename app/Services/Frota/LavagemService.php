<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaLavagem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Lavagem e limpeza: registro, custo e agenda ("repetir a cada N dias"); lavagem atrasada vira pendência. */
class LavagemService
{
    /**
     * @param  array{realizada_em?: ?string, tipo: string, valor?: float|int|string|null, local?: ?string, intervalo_dias?: ?int, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaLavagem
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm lavagem registrada aqui.');
        }
        if ($ativo->baixaVigente()) {
            $this->erro('ativo', 'Este veículo foi baixado.');
        }
        if (! array_key_exists($dados['tipo'] ?? '', FrotaLavagem::TIPOS)) {
            $this->erro('tipo', 'Escolha o tipo de lavagem.');
        }
        $dia = blank($dados['realizada_em'] ?? null) ? now()->startOfDay() : Carbon::parse($dados['realizada_em'])->startOfDay();
        if ($dia->gt(now()->endOfDay())) {
            $this->erro('realizada_em', 'A data da lavagem não pode estar no futuro.');
        }
        $valor = filled($dados['valor'] ?? null) ? (float) str_replace(',', '.', (string) $dados['valor']) : null;
        if ($valor !== null && $valor < 0) {
            $this->erro('valor', 'O valor não pode ser negativo.');
        }
        $intervalo = (int) ($dados['intervalo_dias'] ?? 0);
        if ($intervalo < 0 || $intervalo > 365) {
            $this->erro('intervalo_dias', 'O intervalo deve ficar entre 1 e 365 dias.');
        }

        return FrotaLavagem::create([
            'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'realizada_em' => $dia, 'tipo' => $dados['tipo'], 'valor' => $valor,
            'local' => $dados['local'] ?? null, 'intervalo_dias' => $intervalo ?: null, 'proxima_prevista' => $intervalo ? $dia->copy()->addDays($intervalo) : null,
            'observacoes' => $dados['observacoes'] ?? null, 'registrado_por' => $usuario?->id ?? auth()->id(),
        ]);
    }

    /** Dias de atraso da lavagem agendada (a última lavagem do veículo manda); null se não há agenda ou está em dia. */
    public static function diasAtrasada(Asset $ativo): ?int
    {
        $ultima = FrotaLavagem::where('ativo_id', $ativo->id)->orderByDesc('realizada_em')->orderByDesc('created_at')->first();
        if (! $ultima?->proxima_prevista) {
            return null;
        }
        $dias = (int) $ultima->proxima_prevista->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        return $dias > 0 ? $dias : null;
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
