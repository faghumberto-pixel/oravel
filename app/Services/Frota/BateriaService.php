<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Models\FrotaInstalacaoComponente;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaTesteBateria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Regras das baterias: instalar, remover e testar a tensão (mesmo padrão dos pneus, sem posição). */
class BateriaService
{
    /** @throws ValidationException */
    public function instalar(FrotaBateria $bateria, Asset $ativo, int $odometro, ?User $usuario = null): FrotaInstalacaoComponente
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só é possível instalar bateria em um ativo do tipo Veículo.');
        }
        if ($bateria->situacao !== FrotaBateria::ESTOQUE) {
            $this->erro('bateria', 'Só bateria em estoque pode ser instalada. Situação atual: '.(FrotaBateria::situacaoLabels()[$bateria->situacao] ?? $bateria->situacao).'.');
        }

        return DB::transaction(function () use ($bateria, $ativo, $odometro, $usuario) {
            $instalacao = FrotaInstalacaoComponente::create([
                'tenant_id' => $ativo->tenant_id,
                'ativo_id' => $ativo->id,
                'componente_type' => 'bateria',
                'componente_id' => $bateria->id,
                'posicao' => null,
                'instalado_em' => now(),
                'odometro_instalacao' => $odometro,
                'criado_por' => $usuario?->id,
            ]);

            FrotaLeituraOdometro::registrar($ativo, $odometro, 'bateria', $instalacao->id, null, $usuario?->id);
            $bateria->update(['situacao' => FrotaBateria::MONTADA]);
            app(EstoqueFrotaService::class)->saida($bateria, 1, 'Bateria '.$bateria->rotulo().' instalada em '.($ativo->placa ?: $ativo->name), 'bateria');

            return $instalacao;
        });
    }

    /**
     * Remove a bateria do veículo. Fim da vida útil e descarte = sucateada; troca por garantia e "outro" = volta ao estoque.
     *
     * @throws ValidationException
     */
    public function remover(FrotaBateria $bateria, string $motivo, int $odometro, ?User $usuario = null): FrotaBateria
    {
        if (! array_key_exists($motivo, FrotaBateria::motivoRemocaoLabels())) {
            $this->erro('motivo', 'Motivo de remoção inválido.');
        }

        return DB::transaction(function () use ($bateria, $motivo, $odometro, $usuario) {
            $aberta = $bateria->instalacaoAberta()->with('ativo')->first();
            if (! $aberta) {
                $this->erro('bateria', 'Esta bateria não está instalada em nenhum veículo.');
            }
            if ($odometro < $aberta->odometro_instalacao) {
                $this->erro('odometro', "O odômetro ({$odometro} km) é menor que o da instalação ({$aberta->odometro_instalacao} km).");
            }

            $aberta->update(['removido_em' => now(), 'odometro_remocao' => $odometro, 'motivo_remocao' => $motivo]);
            FrotaLeituraOdometro::registrar($aberta->ativo, $odometro, 'bateria', $aberta->id, null, $usuario?->id);
            $bateria->update(['situacao' => in_array($motivo, ['desgaste', 'descarte'], true) ? FrotaBateria::SUCATEADA : FrotaBateria::ESTOQUE]);
            if ($bateria->situacao === FrotaBateria::ESTOQUE) {
                app(EstoqueFrotaService::class)->entrada($bateria, 1, 'entry_return', 'Bateria '.$bateria->rotulo().' devolvida ao estoque');
            }

            return $bateria;
        });
    }

    /** @throws ValidationException */
    public function registrarTeste(FrotaBateria $bateria, float $tensao, ?int $odometro = null): FrotaTesteBateria
    {
        if ($tensao <= 0 || $tensao > 30) {
            $this->erro('tensao', 'Informe a tensão em volts (por exemplo, 12,6).');
        }

        $aberta = $bateria->instalacaoAberta()->with('ativo')->first();

        return FrotaTesteBateria::create([
            'bateria_id' => $bateria->id,
            'ativo_id' => $aberta?->ativo_id ?? $bateria->instalacoes()->value('ativo_id'),
            'tensao' => $tensao,
            'odometro' => $odometro ?? ($aberta ? (int) floor((float) $aberta->ativo?->odometro_atual) : null),
            'testado_em' => now(),
        ]);
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
