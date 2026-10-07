<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaVinculoMotorista;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Motorista titular do veículo: atribuir (encerra o anterior), encerrar e consultar o titular atual. */
class VinculoMotoristaService
{
    public static function titular(Asset $ativo): ?FrotaVinculoMotorista
    {
        return FrotaVinculoMotorista::where('ativo_id', $ativo->id)->whereNull('fim')->first();
    }

    /** @throws ValidationException */
    public function atribuir(Asset $ativo, string $motoristaId, ?string $inicio = null, ?string $observacoes = null, ?User $usuario = null): FrotaVinculoMotorista
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm motorista titular.');
        }
        $motorista = FleetDriver::find($motoristaId);
        if (! $motorista || ! $motorista->active) {
            $this->erro('motorista_id', 'Escolha um motorista cadastrado e ativo.');
        }
        if ($motorista->cnh_expiry_date && $motorista->cnh_expiry_date->lt(now()->startOfDay())) {
            $this->erro('motorista_id', 'A CNH de '.$motorista->name.' venceu em '.$motorista->cnh_expiry_date->format('d/m/Y').'. Não pode ser titular.');
        }
        $dia = blank($inicio) ? now()->startOfDay() : Carbon::parse($inicio)->startOfDay();
        if ($dia->gt(now()->endOfDay())) {
            $this->erro('inicio', 'A data de início não pode estar no futuro.');
        }
        $atual = self::titular($ativo);
        if ($atual && $atual->motorista_id === $motorista->id) {
            $this->erro('motorista_id', $motorista->name.' já é o titular deste veículo.');
        }
        if ($atual && $dia->lt($atual->inicio)) {
            $this->erro('inicio', 'O novo titular não pode começar antes do atual ('.$atual->inicio->format('d/m/Y').').');
        }

        return DB::transaction(function () use ($ativo, $motorista, $dia, $observacoes, $usuario, $atual) {
            $atual?->update(['fim' => $dia]);

            return FrotaVinculoMotorista::create([
                'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'motorista_id' => $motorista->id, 'inicio' => $dia,
                'observacoes' => $observacoes, 'registrado_por' => $usuario?->id ?? auth()->id(),
            ]);
        });
    }

    /** @throws ValidationException */
    public function encerrar(FrotaVinculoMotorista $vinculo, ?string $fim = null): FrotaVinculoMotorista
    {
        if (! $vinculo->vigente()) {
            $this->erro('fim', 'Este vínculo já foi encerrado.');
        }
        $dia = blank($fim) ? now()->startOfDay() : Carbon::parse($fim)->startOfDay();
        if ($dia->gt(now()->endOfDay()) || $dia->lt($vinculo->inicio)) {
            $this->erro('fim', 'A data de fim deve estar entre o início ('.$vinculo->inicio->format('d/m/Y').') e hoje.');
        }
        $vinculo->update(['fim' => $dia]);

        return $vinculo;
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
