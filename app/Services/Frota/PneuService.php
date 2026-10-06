<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaInspecaoPneu;
use App\Models\FrotaInstalacaoComponente;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPneu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Regras dos pneus: montar, remover, rodízio, recapagem e inspeção. Tudo com histórico de posição e odômetro. */
class PneuService
{
    /** @throws ValidationException */
    public function montar(FrotaPneu $pneu, Asset $ativo, string $posicao, int $odometro, ?User $usuario = null): FrotaInstalacaoComponente
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só é possível montar pneu em um ativo do tipo Veículo.');
        }
        if ($pneu->situacao !== FrotaPneu::ESTOQUE) {
            $this->erro('pneu', 'Só pneu em estoque pode ser montado. Situação atual: '.(FrotaPneu::situacaoLabels()[$pneu->situacao] ?? $pneu->situacao).'.');
        }
        if (! PneuPosicoes::valida($ativo, $posicao)) {
            $this->erro('posicao', 'Posição inválida para este veículo.');
        }

        return DB::transaction(function () use ($pneu, $ativo, $posicao, $odometro, $usuario) {
            $ocupante = $this->ocupante($ativo, $posicao);
            if ($ocupante) {
                $this->erro('posicao', "A posição já tem o pneu {$ocupante->componente?->numero_fogo}. Remova-o antes ou faça um rodízio.");
            }

            $instalacao = $this->abrir($pneu, $ativo, $posicao, $odometro, $usuario);
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'pneu', $instalacao->id, null, $usuario?->id);
            $pneu->update(['situacao' => FrotaPneu::MONTADO]);
            app(EstoqueFrotaService::class)->saida($pneu, 1, 'Pneu '.$pneu->numero_fogo.' montado em '.($ativo->placa ?: $ativo->name), 'pneu');

            return $instalacao;
        });
    }

    /** @throws ValidationException */
    public function remover(FrotaPneu $pneu, string $motivo, int $odometro, ?User $usuario = null): FrotaPneu
    {
        if (! array_key_exists($motivo, FrotaPneu::motivoRemocaoLabels())) {
            $this->erro('motivo', 'Motivo de remoção inválido.');
        }

        return DB::transaction(function () use ($pneu, $motivo, $odometro, $usuario) {
            $aberta = $pneu->instalacaoAberta()->with('ativo')->first();
            if (! $aberta) {
                $this->erro('pneu', 'Este pneu não está montado em nenhum veículo.');
            }

            $this->fechar($aberta, $motivo, $odometro);
            FrotaLeituraOdometro::registrar($aberta->ativo, $odometro, 'pneu', $aberta->id, null, $usuario?->id);

            $pneu->update(['situacao' => match ($motivo) {
                'recapagem' => FrotaPneu::EM_RECAPAGEM,
                'descarte' => FrotaPneu::SUCATEADO,
                default => FrotaPneu::ESTOQUE,
            }]);
            if ($pneu->situacao === FrotaPneu::ESTOQUE) {
                app(EstoqueFrotaService::class)->entrada($pneu, 1, 'entry_return', 'Pneu '.$pneu->numero_fogo.' devolvido ao estoque');
            }

            return $pneu;
        });
    }

    /**
     * Rodízio: leva o pneu para outra posição do MESMO veículo. Se a posição de destino estiver ocupada, os dois
     * pneus trocam de lugar.
     *
     * @throws ValidationException
     */
    public function rodizio(FrotaPneu $pneu, string $novaPosicao, int $odometro, ?User $usuario = null): void
    {
        DB::transaction(function () use ($pneu, $novaPosicao, $odometro, $usuario) {
            $aberta = $pneu->instalacaoAberta()->with('ativo')->first();
            if (! $aberta) {
                $this->erro('pneu', 'Este pneu não está montado em nenhum veículo.');
            }
            $ativo = $aberta->ativo;
            if (! PneuPosicoes::valida($ativo, $novaPosicao)) {
                $this->erro('posicao', 'Posição inválida para este veículo.');
            }
            if ($aberta->posicao === $novaPosicao) {
                $this->erro('posicao', 'O pneu já está nesta posição.');
            }

            $posicaoAntiga = $aberta->posicao;
            $ocupante = $this->ocupante($ativo, $novaPosicao);

            $this->fechar($aberta, 'rodizio', $odometro);
            if ($ocupante) {
                $this->fechar($ocupante, 'rodizio', $odometro);
            }

            $this->abrir($pneu, $ativo, $novaPosicao, $odometro, $usuario);
            if ($ocupante) {
                $this->abrir($ocupante->componente, $ativo, $posicaoAntiga, $odometro, $usuario);
            }

            FrotaLeituraOdometro::registrar($ativo, $odometro, 'pneu', $aberta->id, null, $usuario?->id);
        });
    }

    /**
     * Volta da recapagem: sobe a vida do pneu (novo → 1ª → 2ª → 3ª recapagem), soma o custo e devolve ao estoque.
     *
     * @throws ValidationException
     */
    public function concluirRecapagem(FrotaPneu $pneu, ?float $custo = null, ?float $sulcoNovoMm = null): FrotaPneu
    {
        if ($pneu->situacao !== FrotaPneu::EM_RECAPAGEM) {
            $this->erro('pneu', 'Só um pneu em recapagem pode voltar da recapagem.');
        }

        $proxima = match ($pneu->vida) {
            FrotaPneu::VIDA_NOVO => FrotaPneu::VIDA_RECAPADO_1,
            FrotaPneu::VIDA_RECAPADO_1 => FrotaPneu::VIDA_RECAPADO_2,
            FrotaPneu::VIDA_RECAPADO_2 => FrotaPneu::VIDA_RECAPADO_3,
            default => null,
        };

        if ($proxima === null) {
            $this->erro('pneu', 'Este pneu já foi recapado 3 vezes: o destino é a sucata.');
        }

        $pneu->update([
            'vida' => $proxima,
            'situacao' => FrotaPneu::ESTOQUE,
            'custo' => round((float) $pneu->custo + (float) ($custo ?? 0), 2),
            'sulco_inicial_mm' => $sulcoNovoMm ?? $pneu->sulco_inicial_mm,
        ]);

        return $pneu;
    }

    public function registrarInspecao(FrotaPneu $pneu, ?float $sulcoMm, ?float $pressaoPsi, ?int $odometro = null, ?string $observacao = null): FrotaInspecaoPneu
    {
        if ($sulcoMm === null && $pressaoPsi === null) {
            $this->erro('sulco_mm', 'Informe o sulco ou a pressão.');
        }

        $aberta = $pneu->instalacaoAberta()->with('ativo')->first();

        return FrotaInspecaoPneu::create([
            'pneu_id' => $pneu->id,
            'ativo_id' => $aberta?->ativo_id ?? $pneu->instalacoes()->value('ativo_id'),
            'sulco_mm' => $sulcoMm,
            'pressao_psi' => $pressaoPsi,
            'odometro' => $odometro ?? ($aberta ? (int) floor((float) $aberta->ativo?->odometro_atual) : null),
            'inspecionado_em' => now(),
            'observacao' => $observacao,
        ]);
    }

    private function ocupante(Asset $ativo, string $posicao): ?FrotaInstalacaoComponente
    {
        return FrotaInstalacaoComponente::abertas()
            ->where('ativo_id', $ativo->id)->where('componente_type', 'pneu')->where('posicao', $posicao)
            ->with('componente')->lockForUpdate()->first();
    }

    private function abrir(FrotaPneu $pneu, Asset $ativo, string $posicao, int $odometro, ?User $usuario): FrotaInstalacaoComponente
    {
        return FrotaInstalacaoComponente::create([
            'tenant_id' => $ativo->tenant_id,
            'ativo_id' => $ativo->id,
            'componente_type' => 'pneu',
            'componente_id' => $pneu->id,
            'posicao' => $posicao,
            'instalado_em' => now(),
            'odometro_instalacao' => $odometro,
            'criado_por' => $usuario?->id,
        ]);
    }

    private function fechar(FrotaInstalacaoComponente $instalacao, string $motivo, int $odometro): void
    {
        if ($odometro < $instalacao->odometro_instalacao) {
            $this->erro('odometro', "O odômetro ({$odometro} km) é menor que o da montagem ({$instalacao->odometro_instalacao} km).");
        }

        $instalacao->update(['removido_em' => now(), 'odometro_remocao' => $odometro, 'motivo_remocao' => $motivo]);
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
