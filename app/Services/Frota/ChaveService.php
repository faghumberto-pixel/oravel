<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChave;
use App\Models\FrotaEntregaChave;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Chaves dos veículos: cadastro, entrega (quem levou e por quê) e devolução. Uma chave só está com uma pessoa por vez. */
class ChaveService
{
    /** @throws ValidationException */
    public function criar(Asset $ativo, string $identificacao, ?string $observacoes = null): FrotaChave
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm chaves controladas aqui.');
        }
        $nome = trim($identificacao);
        if ($nome === '') {
            $this->erro('identificacao', 'Identifique a chave (ex.: Chave principal, Reserva).');
        }
        if (FrotaChave::where('ativo_id', $ativo->id)->where('ativo', true)->whereRaw('lower(identificacao) = ?', [mb_strtolower($nome)])->exists()) {
            $this->erro('identificacao', 'Este veículo já tem a chave "'.$nome.'".');
        }

        return FrotaChave::create(['tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'identificacao' => $nome, 'observacoes' => $observacoes]);
    }

    /**
     * @param  array{motorista_id?: ?string, responsavel_nome?: ?string, motivo: string, entregue_em?: ?string, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function entregar(FrotaChave $chave, array $dados, ?User $usuario = null): FrotaEntregaChave
    {
        if (! $chave->ativo) {
            $this->erro('chave', 'Esta chave está desativada.');
        }
        if ($aberta = $chave->entregaAberta()) {
            $this->erro('chave', 'A chave já está com '.$aberta->responsavel().' desde '.$aberta->entregue_em->format('d/m H:i').'. Registre a devolução antes.');
        }
        if (blank($dados['motorista_id'] ?? null) && blank(trim((string) ($dados['responsavel_nome'] ?? '')))) {
            $this->erro('motorista_id', 'Informe quem está retirando a chave.');
        }
        if (filled($dados['motorista_id'] ?? null) && ! FleetDriver::find($dados['motorista_id'])) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }
        if (blank(trim((string) ($dados['motivo'] ?? '')))) {
            $this->erro('motivo', 'Informe o motivo da retirada.');
        }
        $quando = blank($dados['entregue_em'] ?? null) ? now() : Carbon::parse($dados['entregue_em']);
        if ($quando->gt(now()->addMinutes(5))) {
            $this->erro('entregue_em', 'A data e hora não podem estar no futuro.');
        }

        return FrotaEntregaChave::create([
            'tenant_id' => $chave->tenant_id, 'chave_id' => $chave->id, 'motorista_id' => ($dados['motorista_id'] ?? null) ?: null,
            'responsavel_nome' => filled($dados['motorista_id'] ?? null) ? null : trim((string) $dados['responsavel_nome']),
            'entregue_em' => $quando, 'motivo' => trim($dados['motivo']), 'observacoes_entrega' => $dados['observacoes'] ?? null,
            'entregue_por' => $usuario?->id ?? auth()->id(),
        ]);
    }

    /** @throws ValidationException */
    public function devolver(FrotaEntregaChave $entrega, ?string $quando = null, ?string $observacoes = null, ?User $usuario = null): FrotaEntregaChave
    {
        if ($entrega->devolvida_em) {
            $this->erro('devolvida_em', 'Esta chave já foi devolvida.');
        }
        $dia = blank($quando) ? now() : Carbon::parse($quando);
        if ($dia->gt(now()->addMinutes(5))) {
            $this->erro('devolvida_em', 'A data e hora não podem estar no futuro.');
        }
        if ($dia->lt($entrega->entregue_em)) {
            $this->erro('devolvida_em', 'A devolução não pode ser antes da retirada ('.$entrega->entregue_em->format('d/m/Y H:i').').');
        }
        $entrega->update(['devolvida_em' => $dia, 'observacoes_devolucao' => $observacoes, 'devolucao_registrada_por' => $usuario?->id ?? auth()->id()]);

        return $entrega;
    }

    /** @throws ValidationException */
    public function desativar(FrotaChave $chave): FrotaChave
    {
        if ($chave->entregaAberta()) {
            $this->erro('chave', 'A chave está fora: registre a devolução antes de desativar.');
        }
        $chave->update(['ativo' => false]);

        return $chave;
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
