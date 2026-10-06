<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaMulta;
use App\Models\FrotaSaidaVeiculo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Multas da frota: registro (com condutor sugerido pela saída do veículo), indicação do condutor, pagamento, recurso e pontos. */
class MultaService
{
    /**
     * Quem estava com o veículo na hora da infração, segundo a Entrada e Saída de Veículos.
     */
    public function saidaDaInfracao(Asset $ativo, Carbon $quando): ?FrotaSaidaVeiculo
    {
        return FrotaSaidaVeiculo::where('ativo_id', $ativo->id)
            ->where('saida_em', '<=', $quando)
            ->where(fn ($q) => $q->whereNull('retorno_em')->orWhere('retorno_em', '>=', $quando))
            ->latest('saida_em')->first();
    }

    /**
     * @param  array{numero_auto: string, infracao_em: string, descricao: string, gravidade: string, valor: float|int|string, vencimento: string, pontos?: ?int, motorista_id?: ?string, local?: ?string, codigo_infracao?: ?string, prazo_indicacao?: ?string, quem_paga?: ?string, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaMulta
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm multa registrada aqui.');
        }
        if (blank(trim((string) ($dados['numero_auto'] ?? '')))) {
            $this->erro('numero_auto', 'Informe o número do auto de infração.');
        }
        if (FrotaMulta::where('numero_auto', trim($dados['numero_auto']))->exists()) {
            $this->erro('numero_auto', 'Já existe uma multa com este número de auto.');
        }
        if (! array_key_exists($dados['gravidade'] ?? '', FrotaMulta::gravidadeLabels())) {
            $this->erro('gravidade', 'Escolha a gravidade.');
        }
        if (blank(trim((string) ($dados['descricao'] ?? '')))) {
            $this->erro('descricao', 'Descreva a infração.');
        }
        $valor = (float) str_replace(',', '.', (string) ($dados['valor'] ?? 0));
        if ($valor <= 0) {
            $this->erro('valor', 'Informe o valor da multa.');
        }
        $quando = Carbon::parse($dados['infracao_em']);
        if ($quando->gt(now()->addMinutes(5))) {
            $this->erro('infracao_em', 'A data da infração não pode estar no futuro.');
        }

        $saida = $this->saidaDaInfracao($ativo, $quando);
        $motoristaId = $dados['motorista_id'] ?? $saida?->motorista_id;
        if (filled($motoristaId) && ! FleetDriver::find($motoristaId)) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }

        return FrotaMulta::create([
            'tenant_id' => $ativo->tenant_id,
            'ativo_id' => $ativo->id,
            'motorista_id' => $motoristaId,
            'saida_veiculo_id' => $saida?->id,
            'numero_auto' => trim($dados['numero_auto']),
            'infracao_em' => $quando,
            'local' => $dados['local'] ?? null,
            'codigo_infracao' => $dados['codigo_infracao'] ?? null,
            'descricao' => trim($dados['descricao']),
            'gravidade' => $dados['gravidade'],
            'pontos' => (int) ($dados['pontos'] ?? FrotaMulta::PONTOS[$dados['gravidade']]),
            'valor' => $valor,
            'vencimento' => $dados['vencimento'],
            'prazo_indicacao' => $dados['prazo_indicacao'] ?? null,
            'quem_paga' => $dados['quem_paga'] ?? 'empresa',
            'observacoes' => $dados['observacoes'] ?? null,
            'situacao' => filled($motoristaId) ? FrotaMulta::CONDUTOR_INDICADO : FrotaMulta::ABERTA,
            'condutor_indicado_em' => filled($motoristaId) ? now()->toDateString() : null,
            'registrado_por' => $usuario?->id ?? auth()->id(),
        ]);
    }

    /** @throws ValidationException */
    public function indicarCondutor(FrotaMulta $multa, string $motoristaId): FrotaMulta
    {
        $this->exigirAberta($multa);
        if (! FleetDriver::find($motoristaId)) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }
        $multa->update(['motorista_id' => $motoristaId, 'situacao' => FrotaMulta::CONDUTOR_INDICADO, 'condutor_indicado_em' => now()->toDateString()]);

        return $multa;
    }

    /** @throws ValidationException */
    public function pagar(FrotaMulta $multa, ?string $data = null): FrotaMulta
    {
        $this->exigirAberta($multa);
        $dia = $data ? Carbon::parse($data) : now();
        if ($dia->gt(now()->endOfDay())) {
            $this->erro('pago_em', 'A data do pagamento não pode estar no futuro.');
        }
        $multa->update(['situacao' => FrotaMulta::PAGA, 'pago_em' => $dia->toDateString()]);

        return $multa;
    }

    /** @throws ValidationException */
    public function recorrer(FrotaMulta $multa): FrotaMulta
    {
        $this->exigirAberta($multa);
        $multa->update(['situacao' => FrotaMulta::RECORRIDA]);

        return $multa;
    }

    /** @throws ValidationException */
    public function cancelar(FrotaMulta $multa, string $motivo): FrotaMulta
    {
        $this->exigirAberta($multa);
        if (blank(trim($motivo))) {
            $this->erro('motivo', 'Informe o motivo do cancelamento.');
        }
        $multa->update(['situacao' => FrotaMulta::CANCELADA, 'observacoes' => trim(($multa->observacoes ? $multa->observacoes."\n" : '').'Cancelada: '.trim($motivo))]);

        return $multa;
    }

    /** Pontos do motorista nos últimos N meses (multas canceladas não contam). */
    public static function pontosDoMotorista(FleetDriver $motorista, int $meses = 12): int
    {
        return (int) FrotaMulta::where('motorista_id', $motorista->id)->where('situacao', '!=', FrotaMulta::CANCELADA)
            ->where('infracao_em', '>=', now()->subMonths($meses))->sum('pontos');
    }

    private function exigirAberta(FrotaMulta $multa): void
    {
        if ($multa->encerrada()) {
            $this->erro('situacao', 'Esta multa já está '.mb_strtolower(FrotaMulta::situacaoLabels()[$multa->situacao]).'.');
        }
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
