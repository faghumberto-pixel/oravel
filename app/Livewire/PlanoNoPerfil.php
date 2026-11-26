<?php

namespace App\Livewire;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\CotaDiariaIa;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Domain\Intelligence\Trial\Support\Planos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

/** Cartão "Meu plano" na página Minha Conta: em qual degrau o cliente do Intelligence está e como trocar. Só aparece para esses clientes. */
class PlanoNoPerfil extends MyProfileComponent
{
    public static $sort = 5;

    public static function canView(): bool
    {
        $tenantId = Auth::user()?->tenant_id;

        return $tenantId && TrialCiclo::doTenant($tenantId) !== null;
    }

    public function render(): View
    {
        $tenantId = (string) Auth::user()->tenant_id;
        $trial = TrialCiclo::doTenant($tenantId);
        $cota = app(CotaDiariaIa::class);

        return view('livewire.plano-no-perfil', [
            'trial' => $trial,
            'atual' => $this->descrever($trial),
            'degraus' => $this->degraus($trial),
            'limiteIa' => $cota->limite($tenantId),
            'usadasIa' => $cota->usadasHoje($tenantId),
        ]);
    }

    /** @return array{nome: string, situacao: string, cor: string, detalhes: list<string>, contratado: bool, modulos: list<string>} */
    private function descrever(IntelligenceTrial $trial): array
    {
        $status = $trial->statusAtual();
        $efetivo = Planos::efetivo($trial);
        $contratado = $efetivo !== null && ! $trial->canceled_at;
        $detalhes = [];

        if ($efetivo) {
            $nome = $efetivo['nome'];
            $detalhes[] = 'Valor: R$ '.number_format($efetivo['preco'], 2, ',', '.').' por mês';
            if ($trial->canceled_at) {
                $detalhes[] = 'Cancelado. Acesso até '.($trial->access_until?->format('d/m/Y') ?? '-').'.';
            } elseif ($trial->billing_starts_on && $trial->billing_starts_on->isFuture()) {
                $detalhes[] = 'A cobrança começa em '.$trial->billing_starts_on->format('d/m/Y').' (até lá, sem custo).';
            }
            if ($trial->plano_proximo_chave && $trial->plano_proximo_em?->isFuture() && ($prox = Planos::achar($trial->plano_proximo_chave))) {
                $detalhes[] = 'Troca para '.$prox['nome'].' agendada para '.$trial->plano_proximo_em->format('d/m/Y').'.';
            }
        } else {
            $nome = 'Teste grátis';
            $detalhes[] = $trial->trial_ends_at
                ? $trial->diasRestantes().' dia(s) restantes, até '.$trial->trial_ends_at->format('d/m/Y').'.'
                : 'Os '.config('intelligence_trial.dias').' dias do teste começam no seu próximo acesso.';
        }

        return [
            'nome' => $nome, 'situacao' => $status->rotulo(), 'cor' => $status->cor(), 'detalhes' => $detalhes, 'contratado' => $contratado,
            'modulos' => array_map(Planos::rotuloModulo(...), $efetivo['modulos'] ?? ['base']),
        ];
    }

    /**
     * Todos os degraus com o que o cliente pode fazer em cada um.
     *
     * @return list<array<string, mixed>>
     */
    private function degraus(IntelligenceTrial $trial): array
    {
        $efetivo = Planos::efetivo($trial);
        $podeTrocar = $efetivo !== null && ! $trial->canceled_at;
        $lista = [];

        foreach (Planos::todos() as $p) {
            $atual = $efetivo && $efetivo['chave'] === $p['chave'];
            $acao = match (true) {
                $atual => null,
                ! $p['contratavel'] => 'em_breve',
                ! $podeTrocar => 'contratar',
                $efetivo['nivel'] < $p['nivel'] => 'subir',
                default => 'descer',
            };
            $lista[] = $p + ['atual' => $atual, 'acao' => $acao, 'modulos_rotulos' => array_map(Planos::rotuloModulo(...), $p['modulos_novos'])];
        }

        return $lista;
    }
}
