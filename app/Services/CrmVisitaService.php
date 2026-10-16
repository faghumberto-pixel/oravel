<?php

namespace App\Services;

use App\Models\CrmLeadInteraction;
use App\Models\CrmVeiculo;
use App\Models\CrmVisita;
use App\Models\User;
use App\Support\CrmRota;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo da visita de campo: check-in (localização + distância até o endereço), check-out (resultado, km,
 * histórico do lead) e cancelamento. Vendedor só mexe nas próprias visitas; "ver todos" mexe em qualquer uma.
 */
class CrmVisitaService
{
    /** Acima disto o check-in avisa que o vendedor está longe do endereço (registra mesmo assim). */
    public const DISTANCIA_AVISO_M = 500;

    /**
     * @return array{visita: CrmVisita, avisos: list<string>}
     *
     * @throws \DomainException status inválido ou visita de outro vendedor
     */
    public function checkin(CrmVisita $visita, User $user, ?float $lat = null, ?float $lng = null, ?string $veiculoId = null): array
    {
        $this->autorizar($visita, $user);

        if ($visita->status !== CrmVisita::STATUS_AGENDADA) {
            throw new \DomainException('Só dá para fazer check-in numa visita agendada.');
        }

        $avisos = [];
        $dados = ['status' => CrmVisita::STATUS_EM_ANDAMENTO, 'checkin_em' => now()];

        if ($lat !== null && $lng !== null) {
            $dados['checkin_lat'] = $lat;
            $dados['checkin_lng'] = $lng;

            if ($visita->temCoordenadas()) {
                $distancia = (int) round(CrmRota::haversine($lat, $lng, (float) $visita->latitude, (float) $visita->longitude));
                $dados['checkin_distancia_m'] = $distancia;

                if ($distancia > self::DISTANCIA_AVISO_M) {
                    $avisos[] = 'Você está a cerca de '.number_format($distancia / 1000, 1, ',', '.').' km do endereço da visita.';
                }
            }
        } else {
            $avisos[] = 'Check-in registrado sem localização.';
        }

        $veiculoId ??= $visita->crm_veiculo_id;

        if ($veiculoId && ($veiculo = CrmVeiculo::find($veiculoId))) {
            $dados['crm_veiculo_id'] = $veiculo->id;
            $dados['km_inicio'] ??= $visita->km_inicio ?? $veiculo->km_atual;

            if ($emUso = $veiculo->emUsoPorOutro($visita->user_id)) {
                $avisos[] = "O veículo {$veiculo->placa} está em uso em outra visita em andamento.";
            }

            if ($veiculo->situacaoVencimentos() === 'vencido') {
                $avisos[] = "O veículo {$veiculo->placa} está com documento vencido.";
            }
        }

        $visita->update($dados);

        return ['visita' => $visita->refresh(), 'avisos' => $avisos];
    }

    /**
     * @param  array{resultado: string, km_fim?: int|null, proximo_passo?: string|null, proximo_contato?: string|null, lat?: float|null, lng?: float|null, status?: string}  $dados
     *
     * @throws \DomainException status inválido ou visita de outro vendedor
     * @throws ValidationException resultado vazio ou km final menor que o inicial
     */
    public function checkout(CrmVisita $visita, User $user, array $dados): CrmVisita
    {
        $this->autorizar($visita, $user);

        if ($visita->status !== CrmVisita::STATUS_EM_ANDAMENTO) {
            throw new \DomainException('Só dá para finalizar uma visita em andamento (faça o check-in antes).');
        }

        if (trim((string) ($dados['resultado'] ?? '')) === '') {
            throw ValidationException::withMessages(['resultado' => 'Descreva o resultado da visita.']);
        }

        $kmFim = $dados['km_fim'] ?? null;

        if ($kmFim !== null && $visita->km_inicio !== null && (int) $kmFim < $visita->km_inicio) {
            throw ValidationException::withMessages(['km_fim' => 'O km final não pode ser menor que o km inicial.']);
        }

        $status = ($dados['status'] ?? CrmVisita::STATUS_REALIZADA) === CrmVisita::STATUS_NAO_ENCONTROU
            ? CrmVisita::STATUS_NAO_ENCONTROU : CrmVisita::STATUS_REALIZADA;

        $visita->update([
            'status' => $status,
            'checkout_em' => now(),
            'checkout_lat' => $dados['lat'] ?? null,
            'checkout_lng' => $dados['lng'] ?? null,
            'km_fim' => $kmFim,
            'resultado' => $dados['resultado'],
            'proximo_passo' => $dados['proximo_passo'] ?? null,
            'proximo_contato' => $dados['proximo_contato'] ?? null,
        ]);

        if ($kmFim !== null && $visita->crm_veiculo_id && ($veiculo = CrmVeiculo::find($visita->crm_veiculo_id)) && $kmFim > $veiculo->km_atual) {
            $veiculo->update(['km_atual' => (int) $kmFim]);
        }

        if ($visita->crm_lead_id && ($lead = $visita->lead)) {
            CrmLeadInteraction::create([
                'tenant_id' => $visita->tenant_id,
                'crm_lead_id' => $lead->id,
                'user_id' => $user->id,
                'channel' => CrmLeadInteraction::CHANNEL_PRESENCIAL,
                'contact_date' => now(),
                'summary' => 'Visita: '.$dados['resultado'],
                'next_action' => $dados['proximo_passo'] ?? null,
                'next_followup_date' => $dados['proximo_contato'] ?? null,
                'stage_at_time' => $lead->stage,
            ]);
        }

        return $visita->refresh();
    }

    public function cancelar(CrmVisita $visita, User $user, ?string $motivo = null): CrmVisita
    {
        $this->autorizar($visita, $user);

        if (! in_array($visita->status, [CrmVisita::STATUS_AGENDADA, CrmVisita::STATUS_EM_ANDAMENTO], true)) {
            throw new \DomainException('Esta visita já foi encerrada.');
        }

        $visita->update(['status' => CrmVisita::STATUS_CANCELADA, 'observacoes' => trim($visita->observacoes."\nCancelada: ".$motivo)]);

        return $visita->refresh();
    }

    private function autorizar(CrmVisita $visita, User $user): void
    {
        if ((string) $visita->tenant_id !== (string) $user->tenant_id && ! $user->isSuperAdmin()) {
            throw new \DomainException('Visita de outra empresa.');
        }

        if ((string) $visita->user_id !== (string) $user->id && ! $user->isSuperAdmin() && ! $user->canSeeAllCrmLeads()) {
            throw new \DomainException('Você só pode mexer nas suas próprias visitas.');
        }
    }
}
