<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChecklist;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaSinistro;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Entrada e saída de veículos: quem levou, para quê, km, e as travas (checklist bloqueado, já fora, CNH vencida). */
class SaidaVeiculoService
{
    /**
     * @param  array{finalidade: string, motivo: string, saida_em?: ?string, motorista_id: string, destino?: ?string, cliente_id?: ?string, ordem_servico_id?: ?string, odometro: int|string, combustivel?: ?string, observacoes?: ?string, justificativa_odometro?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrarSaida(Asset $ativo, array $dados, ?User $usuario = null): FrotaSaidaVeiculo
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm entrada e saída registradas aqui.');
        }
        if ($ativo->baixaVigente()) {
            $this->erro('ativo', 'Este veículo foi baixado e não pode registrar saída.');
        }
        if (! array_key_exists($dados['finalidade'] ?? '', FrotaSaidaVeiculo::finalidadeLabels())) {
            $this->erro('finalidade', 'Escolha a finalidade da saída.');
        }
        if (blank(trim((string) ($dados['destino'] ?? '')))) {
            $this->erro('destino', 'Informe o destino.');
        }
        if (blank(trim((string) ($dados['motivo'] ?? '')))) {
            $this->erro('motivo', 'Informe o motivo da saída.');
        }
        $saidaEm = $this->dataHora($dados['saida_em'] ?? null, 'saida_em');
        if (blank($dados['motorista_id'] ?? null)) {
            $this->erro('motorista_id', 'Escolha o motorista (cadastrado em Logística → Frota → Motoristas).');
        }
        if ($fora = FrotaSaidaVeiculo::fora()->where('ativo_id', $ativo->id)->first()) {
            $this->erro('ativo', 'Este veículo já está fora desde '.$fora->saida_em->format('d/m H:i').' (com '.$fora->condutor().'). Registre a entrada antes.');
        }
        if ($parado = SinistroService::paradoPorSinistro($ativo)) {
            $this->erro('ativo', 'Veículo parado por sinistro ('.FrotaSinistro::tipoLabels()[$parado->tipo].' em '.$parado->ocorrido_em->format('d/m/Y').'). Registre "voltou a rodar" antes de sair.');
        }
        if ($bloqueio = $ativo->bloqueioChecklistFrota()) {
            $this->erro('ativo', 'Veículo bloqueado pelo checklist desde '.$bloqueio->concluido_em?->format('d/m H:i').'. Um responsável precisa liberá-lo antes da saída.');
        }
        $motorista = filled($dados['motorista_id'] ?? null) ? FleetDriver::find($dados['motorista_id']) : null;
        if (filled($dados['motorista_id'] ?? null) && ! $motorista) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }
        if ($motorista?->cnh_expiry_date && $motorista->cnh_expiry_date->lt(now()->startOfDay())) {
            $this->erro('motorista_id', 'A CNH de '.$motorista->name.' venceu em '.$motorista->cnh_expiry_date->format('d/m/Y').'. Não pode conduzir o veículo.');
        }

        return DB::transaction(function () use ($ativo, $dados, $usuario, $motorista, $saidaEm) {
            $odometro = (int) $dados['odometro'];

            $saida = FrotaSaidaVeiculo::create([
                'tenant_id' => $ativo->tenant_id,
                'ativo_id' => $ativo->id,
                'motorista_id' => $motorista?->id,
                'condutor_nome' => null,
                'finalidade' => $dados['finalidade'],
                'destino' => trim($dados['destino']),
                'motivo' => trim($dados['motivo']),
                'cliente_id' => $dados['cliente_id'] ?? null,
                'ordem_servico_id' => $dados['ordem_servico_id'] ?? null,
                'saida_em' => $saidaEm,
                'odometro_saida' => $odometro,
                'combustivel_saida' => $dados['combustivel'] ?? null,
                'checklist_saida_id' => $this->ultimoChecklist($ativo, FrotaChecklist::TIPO_SAIDA)?->id,
                'observacoes_saida' => $dados['observacoes'] ?? null,
                'registrado_por' => $usuario?->id ?? auth()->id(),
            ]);
            // Recusa (km menor sem justificativa) desfaz a saída inteira.
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'saida_veiculo', $saida->id, $dados['justificativa_odometro'] ?? null, $usuario?->id);

            return $saida;
        });
    }

    /**
     * @param  array{retorno_em?: ?string, odometro: int|string, combustivel?: ?string, observacoes?: ?string, justificativa_odometro?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrarEntrada(FrotaSaidaVeiculo $saida, array $dados, ?User $usuario = null): FrotaSaidaVeiculo
    {
        if (! $saida->estaFora()) {
            $this->erro('saida', 'Este veículo já teve a entrada registrada.');
        }
        $retornoEm = $this->dataHora($dados['retorno_em'] ?? null, 'retorno_em');
        if ($retornoEm->lt($saida->saida_em)) {
            $this->erro('retorno_em', 'A entrada não pode ser antes da saída ('.$saida->saida_em->format('d/m/Y H:i').').');
        }
        $odometro = (int) $dados['odometro'];
        if ($odometro < $saida->odometro_saida && blank($dados['justificativa_odometro'] ?? null)) {
            $this->erro('odometro', "O odômetro da entrada ({$odometro} km) é menor que o da saída ({$saida->odometro_saida} km). Confira o valor ou informe a justificativa.");
        }

        return DB::transaction(function () use ($saida, $dados, $usuario, $odometro, $retornoEm) {
            $ativo = $saida->veiculo;
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'entrada_veiculo', $saida->id, $dados['justificativa_odometro'] ?? null, $usuario?->id);

            $saida->update([
                'retorno_em' => $retornoEm,
                'odometro_retorno' => $odometro,
                'combustivel_retorno' => $dados['combustivel'] ?? null,
                'checklist_retorno_id' => $this->ultimoChecklist($ativo, FrotaChecklist::TIPO_RETORNO, $saida->saida_em)?->id,
                'observacoes_retorno' => $dados['observacoes'] ?? null,
                'retorno_registrado_por' => $usuario?->id ?? auth()->id(),
            ]);

            return $saida;
        });
    }

    /** Checklist concluído do tipo pedido (para o retorno, só os feitos depois da saída). */
    private function ultimoChecklist(Asset $ativo, string $tipo, ?\DateTimeInterface $depoisDe = null): ?FrotaChecklist
    {
        return FrotaChecklist::where('ativo_id', $ativo->id)->where('tipo', $tipo)->whereNotNull('concluido_em')
            ->when($depoisDe, fn ($q) => $q->where('concluido_em', '>=', $depoisDe))
            ->when(! $depoisDe, fn ($q) => $q->where('concluido_em', '>=', now()->subHours(12)))
            ->latest('concluido_em')->first();
    }

    /** Data e hora informadas (padrão: agora); não aceita data no futuro. */
    private function dataHora(mixed $valor, string $campo): Carbon
    {
        $momento = blank($valor) ? now() : Carbon::parse($valor);
        if ($momento->gt(now()->addMinutes(5))) {
            $this->erro($campo, 'A data e hora não podem estar no futuro.');
        }

        return $momento;
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
