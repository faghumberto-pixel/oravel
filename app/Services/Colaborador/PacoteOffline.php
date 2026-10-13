<?php

namespace App\Services\Colaborador;

use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaPercurso;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaVinculoMotorista;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Colaborador\SituacaoOs;
use Illuminate\Support\Facades\Gate;

/**
 * Pacote de dados que o celular guarda para trabalhar sem internet: todos os equipamentos da empresa (formato leve),
 * as OS em aberto + as concluídas recentes de cada um e, em detalhe, as OS do próprio técnico.
 * Respeita as mesmas permissões das telas online (Gate) e o tenant (BelongsToTenant).
 */
class PacoteOffline
{
    private const RECENTES_POR_ATIVO = 5;

    public function montar(User $user): array
    {
        $pacote = ['gerado_em' => now()->toIso8601String(), 'usuario_id' => $user->id, 'ativos' => [], 'os' => [], 'minhas' => [], 'frota' => null];

        if (! $user->tenant_id && ! $user->isSuperAdmin()) {
            return $pacote;
        }

        if (Gate::forUser($user)->allows('viewAny', Asset::class)) {
            $pacote['ativos'] = Asset::query()->select('id', 'name', 'patrimonio', 'placa', 'tag', 'serial_number', 'status', 'grupo')
                ->orderBy('name')->limit(20000)->get()
                ->map(fn (Asset $a) => [
                    'id' => $a->id, 'n' => $a->name, 'p' => $a->patrimonio, 'pl' => $a->placa, 't' => $a->tag, 's' => $a->serial_number,
                    'st' => $a->status, 'v' => $a->grupo === Asset::GRUPO_VEICULO,
                ])->all();
        }

        if (Gate::forUser($user)->allows('viewAny', MaintenanceOrder::class)) {
            $campos = ['id', 'os_number', 'asset_id', 'status', 'internal_status', 'created_at', 'updated_at', 'finished_at', 'scheduled_at', 'description', 'technician_id'];

            $abertas = MaintenanceOrder::query()->whereNotIn('status', SituacaoOs::FECHADAS)->with('technician:id,name')->get($campos);
            $recentes = MaintenanceOrder::query()->whereIn('status', SituacaoOs::FECHADAS)->where('created_at', '>=', now()->subDays(180))
                ->with('technician:id,name')->orderByDesc('created_at')->limit(3000)->get($campos)
                ->groupBy('asset_id')->flatMap(fn ($grupo) => $grupo->take(self::RECENTES_POR_ATIVO));

            foreach ($abertas->concat($recentes) as $os) {
                $fechada = in_array($os->status, SituacaoOs::FECHADAS, true);
                $situacao = SituacaoOs::daOs($os);
                $pacote['os'][$os->id] = [
                    'id' => $os->id, 'num' => $os->os_number, 'ativo' => $os->asset_id,
                    'sit' => $situacao['rotulo'], 'cor' => $situacao['cor'], 'fechada' => $fechada,
                    'ab' => $os->created_at?->toIso8601String(),
                    'fim' => $fechada ? ($os->finished_at ?? $os->updated_at)?->toIso8601String() : null,
                    'ag' => $os->scheduled_at?->toIso8601String(),
                    'tec' => $os->technician?->name, 'tec_id' => $os->technician_id,
                    'prob' => $os->description,
                ];
            }

            // Detalhe das OS do técnico logado: o que o formulário offline de execução precisa.
            $pacote['minhas'] = MaintenanceOrder::query()->where('technician_id', $user->id)->whereNotIn('status', SituacaoOs::FECHADAS)
                ->with(['asset:id,name,patrimonio,placa,last_horimetro', 'checklists' => fn ($q) => $q->where('is_template', false)->orderBy('section')->orderBy('item_name')])
                ->get()->filter(fn ($os) => Gate::forUser($user)->allows('update', $os))
                ->map(fn (MaintenanceOrder $os) => [
                    'id' => $os->id, 'num' => $os->os_number, 'status' => $os->status,
                    'ativo' => $os->asset ? ['id' => $os->asset->id, 'n' => $os->asset->name, 'p' => $os->asset->patrimonio, 'pl' => $os->asset->placa] : null,
                    'horimetro_anterior' => $os->asset?->last_horimetro,
                    'horimetro_entry' => $os->horimetro_entry, 'fuel_level' => $os->fuel_level, 'technical_notes' => $os->technical_notes,
                    'tem_assinatura_tecnico' => filled($os->technician_signature), 'tem_assinatura_cliente' => filled($os->client_signature),
                    'checklist' => $os->checklists->map(fn ($i) => [
                        'id' => $i->id, 'nome' => $i->item_name, 'secao' => $i->section ?: $i->category, 'status' => $i->status, 'notas' => $i->notes,
                    ])->values()->all(),
                ])->values()->all();
        }

        if (Gate::forUser($user)->allows('viewAny', FrotaSaidaVeiculo::class) || Gate::forUser($user)->allows('viewAny', FrotaAbastecimento::class)) {
            $pacote['frota'] = $this->frota($user);
        }

        return $pacote;
    }

    /** Logística offline: veículos (com situação e odômetro), motoristas, clientes, percursos e rótulos dos formulários. */
    private function frota(User $user): array
    {
        $fora = FrotaSaidaVeiculo::fora()->with(['motorista', 'equipamento'])->get()->keyBy('ativo_id');
        $ultimos = FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->selectRaw('DISTINCT ON (ativo_id) *')->orderBy('ativo_id')->orderByDesc('retorno_em')->get()->keyBy('ativo_id');
        $titulares = FrotaVinculoMotorista::query()->whereNull('fim')->with('motorista')->get()->keyBy('ativo_id');

        $veiculos = Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->naoBaixado()->orderBy('placa')->get()->map(function (Asset $a) use ($fora, $ultimos, $titulares) {
            $ultimo = $ultimos->get($a->id);
            $aberta = $fora->get($a->id);

            return [
                'id' => $a->id, 'n' => $a->name, 'pl' => $a->placa, 'p' => $a->patrimonio, 'odo' => (int) floor((float) $a->odometro_atual),
                'local' => ! $ultimo || $ultimo->destino_tipo === 'empresa'
                    ? ['tipo' => 'empresa', 'cliente_id' => null, 'rotulo' => 'Empresa', 'texto' => null]
                    : ['tipo' => $ultimo->destino_tipo, 'cliente_id' => $ultimo->destino_tipo === 'cliente' ? $ultimo->cliente_id : null, 'rotulo' => $ultimo->destino, 'texto' => $ultimo->destino_tipo === 'outro' ? $ultimo->destino : null],
                'aberta' => $aberta ? [
                    'id' => $aberta->id, 'saida_em' => $aberta->saida_em?->toIso8601String(), 'trecho' => $aberta->trecho(), 'destino' => $aberta->destino, 'destino_tipo' => $aberta->destino_tipo, 'cliente_id' => $aberta->cliente_id,
                    'condutor' => $aberta->condutor(), 'motivo' => $aberta->motivo, 'odo_saida' => $aberta->odometro_saida, 'equipamento' => $aberta->equipamento?->name,
                ] : null,
                'titular' => ($t = $titulares->get($a->id)) ? ['motorista_id' => $t->motorista_id, 'nome' => $t->motorista?->name] : null,
            ];
        })->all();

        return [
            'perm' => [
                'saida' => Gate::forUser($user)->allows('create', FrotaSaidaVeiculo::class),
                'abastecimento' => Gate::forUser($user)->allows('create', FrotaAbastecimento::class),
            ],
            'veiculos' => $veiculos,
            'motoristas' => FleetDriver::query()->where('active', true)->orderBy('name')->get()->map(fn (FleetDriver $m) => [
                'id' => $m->id, 'nome' => $m->name, 'cnh_vencida' => (bool) ($m->cnh_expiry_date && $m->cnh_expiry_date->lt(now()->startOfDay())),
            ])->all(),
            'clientes' => Client::query()->orderBy('name')->limit(5000)->get(['id', 'name'])->map(fn (Client $c) => ['id' => $c->id, 'nome' => $c->name])->all(),
            'percursos' => FrotaPercurso::query()->where('ativo', true)->orderBy('nome')->get()->map(fn (FrotaPercurso $p) => [
                'id' => $p->id, 'nome' => $p->nome, 'previsto' => $p->km_previsto !== null ? (float) $p->km_previsto : null,
                'a' => ['tipo' => $p->local_a_tipo, 'cliente_id' => $p->local_a_cliente_id, 'texto' => $p->local_a_texto],
                'b' => ['tipo' => $p->local_b_tipo, 'cliente_id' => $p->local_b_cliente_id, 'texto' => $p->local_b_texto],
            ])->all(),
            'rotulos' => [
                'finalidades' => FrotaSaidaVeiculo::finalidadeLabels(), 'combustiveis' => FrotaAbastecimento::combustivelLabels(), 'origens' => FrotaAbastecimento::origemLabels(),
            ],
        ];
    }
}
