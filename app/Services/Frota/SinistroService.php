<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaSinistro;
use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Sinistros da frota: registro (condutor sugerido pela saída), OS de reparo, volta a rodar, encerramento e cancelamento. */
class SinistroService
{
    /**
     * @param  array<string, mixed>  $dados  tipo, ocorrido_em, descricao + demais campos do modelo (local, culpa, bo_numero, seguradora...)
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaSinistro
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm sinistro registrado aqui.');
        }
        if (! array_key_exists($dados['tipo'] ?? '', FrotaSinistro::tipoLabels())) {
            $this->erro('tipo', 'Escolha o tipo da ocorrência.');
        }
        if (blank(trim((string) ($dados['descricao'] ?? '')))) {
            $this->erro('descricao', 'Descreva o que aconteceu.');
        }
        $quando = Carbon::parse($dados['ocorrido_em'] ?? null);
        if ($quando->gt(now()->addMinutes(5))) {
            $this->erro('ocorrido_em', 'A data da ocorrência não pode estar no futuro.');
        }
        $saida = app(MultaService::class)->saidaDaInfracao($ativo, $quando);
        $motoristaId = $dados['motorista_id'] ?? $saida?->motorista_id;
        if (filled($motoristaId) && ! FleetDriver::find($motoristaId)) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }
        $parado = (bool) ($dados['veiculo_parado'] ?? false);

        return FrotaSinistro::create(array_merge($this->limpar($dados), [
            'tenant_id' => $ativo->tenant_id,
            'ativo_id' => $ativo->id,
            'motorista_id' => $motoristaId,
            'saida_veiculo_id' => $saida?->id,
            'ocorrido_em' => $quando,
            'descricao' => trim($dados['descricao']),
            'veiculo_parado' => $parado,
            'parado_desde' => $parado ? ($dados['parado_desde'] ?? $quando) : null,
            'voltou_a_rodar_em' => null,
            'situacao' => FrotaSinistro::ABERTO,
            'registrado_por' => $usuario?->id ?? auth()->id(),
        ]));
    }

    /** Abre a OS corretiva de reparo (uma por sinistro) e muda a situação para "Em reparo". */
    public function gerarOs(FrotaSinistro $sinistro): MaintenanceOrder
    {
        $this->exigirAberto($sinistro);
        if ($sinistro->ordem_servico_id) {
            $this->erro('ordem_servico_id', 'Este sinistro já tem a OS '.$sinistro->ordemServico?->os_number.'.');
        }
        $ativo = $sinistro->veiculo;
        $os = MaintenanceOrder::create([
            'tenant_id' => $ativo->tenant_id,
            'asset_id' => $ativo->id,
            'client_id' => $ativo->client_id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => 'Reparo de sinistro ('.FrotaSinistro::tipoLabels()[$sinistro->tipo].' em '.$sinistro->ocorrido_em->format('d/m/Y').'): '.$sinistro->descricao,
        ]);
        $sinistro->update(['ordem_servico_id' => $os->id, 'situacao' => FrotaSinistro::EM_REPARO]);

        return $os;
    }

    /** @throws ValidationException */
    public function registrarOrcamento(FrotaSinistro $sinistro, float|string $valor, ?float $franquia = null): FrotaSinistro
    {
        $this->exigirAberto($sinistro);
        $v = (float) str_replace(',', '.', (string) $valor);
        if ($v <= 0) {
            $this->erro('valor_orcamento', 'Informe o valor do orçamento.');
        }
        $sinistro->update(['valor_orcamento' => $v, 'valor_franquia' => $franquia, 'situacao' => $sinistro->situacao === FrotaSinistro::ABERTO ? FrotaSinistro::EM_ORCAMENTO : $sinistro->situacao]);

        return $sinistro;
    }

    /** O veículo voltou a rodar: fecha a contagem de dias parado. */
    public function voltouARodar(FrotaSinistro $sinistro, ?string $quando = null): FrotaSinistro
    {
        $this->exigirAberto($sinistro);
        if (! $sinistro->parado()) {
            $this->erro('veiculo_parado', 'Este sinistro não tem veículo parado.');
        }
        $dia = $quando ? Carbon::parse($quando) : now();
        if ($dia->lt($sinistro->parado_desde)) {
            $this->erro('voltou_a_rodar_em', 'A volta a rodar não pode ser antes da parada ('.$sinistro->parado_desde->format('d/m/Y H:i').').');
        }
        if ($dia->gt(now()->addMinutes(5))) {
            $this->erro('voltou_a_rodar_em', 'A data não pode estar no futuro.');
        }
        $sinistro->update(['voltou_a_rodar_em' => $dia]);

        return $sinistro;
    }

    /** @throws ValidationException */
    public function encerrar(FrotaSinistro $sinistro): FrotaSinistro
    {
        $this->exigirAberto($sinistro);
        if ($sinistro->parado()) {
            $this->erro('veiculo_parado', 'O veículo ainda está parado: registre "voltou a rodar" antes de encerrar.');
        }
        $sinistro->update(['situacao' => FrotaSinistro::ENCERRADO, 'encerrado_em' => now()]);

        return $sinistro;
    }

    /** @throws ValidationException */
    public function cancelar(FrotaSinistro $sinistro, string $motivo): FrotaSinistro
    {
        $this->exigirAberto($sinistro);
        if (blank(trim($motivo))) {
            $this->erro('motivo', 'Informe o motivo do cancelamento.');
        }
        $sinistro->update(['situacao' => FrotaSinistro::CANCELADO, 'encerrado_em' => now(), 'observacoes' => trim(($sinistro->observacoes ? $sinistro->observacoes."\n" : '').'Cancelado: '.trim($motivo))]);

        return $sinistro;
    }

    /** O veículo está parado por algum sinistro? (usado para travar a saída) */
    public static function paradoPorSinistro(Asset $ativo): ?FrotaSinistro
    {
        return FrotaSinistro::where('ativo_id', $ativo->id)->where('veiculo_parado', true)->whereNull('voltou_a_rodar_em')
            ->where('situacao', '!=', FrotaSinistro::CANCELADO)->first();
    }

    /** @param  array<string, mixed>  $dados */
    private function limpar(array $dados): array
    {
        return collect($dados)->only(['tipo', 'local', 'culpa', 'houve_vitima', 'bo_numero', 'seguradora', 'apolice', 'numero_sinistro_seguradora', 'valor_orcamento', 'valor_franquia', 'observacoes'])->all();
    }

    private function exigirAberto(FrotaSinistro $sinistro): void
    {
        if ($sinistro->encerrado()) {
            $this->erro('situacao', 'Este sinistro já está '.mb_strtolower(FrotaSinistro::situacaoLabels()[$sinistro->situacao]).'.');
        }
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
