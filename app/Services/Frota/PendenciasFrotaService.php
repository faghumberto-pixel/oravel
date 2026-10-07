<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaMulta;
use App\Models\FrotaPlanoRevisao;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaSinistro;
use App\Models\MaintenanceOrder;
use App\Models\Part;
use App\Models\PartCategory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Pendências da Frota, calculadas ao vivo (nada é gravado): checklist, pneus, baterias, óleo, vencimentos de
 * documentos e estoque abaixo do mínimo. Cada pendência tem uma chave estável, usada para não abrir OS em duplicidade.
 */
class PendenciasFrotaService
{
    public const CRITICA = 'critica';

    public const ATENCAO = 'atencao';

    /** Documento vence em até N dias = atenção; já vencido = crítica. */
    public const AVISO_DOCUMENTO_DIAS = 30;

    /** @return array<string, string> */
    public static function categorias(): array
    {
        return ['checklist' => 'Checklist', 'pneu' => 'Pneus', 'bateria' => 'Baterias', 'oleo' => 'Óleo', 'saida' => 'Entrada e saída', 'multa' => 'Multas', 'sinistro' => 'Sinistros', 'consumo' => 'Consumo', 'revisao' => 'Revisões', 'cnh' => 'CNH e pontos', 'documento' => 'Documentos', 'estoque' => 'Estoque'];
    }

    /**
     * @return Collection<int, array{chave: string, categoria: string, gravidade: string, ativo_id: ?string, placa: ?string, veiculo: ?string, mensagem: string}>
     */
    public function todas(?Asset $so = null): Collection
    {
        $veiculos = $so ? collect([$so]) : Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get();
        $lista = collect();

        foreach ($veiculos as $v) {
            $lista = $lista->concat($this->doVeiculo($v));
        }
        if (! $so) {
            $lista = $lista->concat($this->estoqueBaixo())->concat($this->motoristas());
        }

        return $lista->sortBy(fn (array $p) => ($p['gravidade'] === self::CRITICA ? '0' : '1').$p['placa'])->values();
    }

    public function contar(): int
    {
        return $this->todas()->count();
    }

    public function criticas(): int
    {
        return $this->todas()->where('gravidade', self::CRITICA)->count();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function doVeiculo(Asset $v): Collection
    {
        $p = collect();
        $add = function (string $categoria, string $gravidade, string $detalhe, string $mensagem) use ($v, $p) {
            $p->push([
                'chave' => md5($v->id.'|'.$categoria.'|'.$detalhe),
                'categoria' => $categoria, 'gravidade' => $gravidade, 'ativo_id' => $v->id,
                'placa' => $v->placa, 'veiculo' => $v->name, 'mensagem' => $mensagem,
            ]);
        };

        if ($b = $v->bloqueioChecklistFrota()) {
            $add('checklist', self::CRITICA, 'bloqueio', 'Veículo bloqueado pelo checklist desde '.$b->concluido_em?->format('d/m H:i').': não pode sair.');
        }
        if ($v->checklistCompletoVencido()) {
            $add('checklist', self::ATENCAO, 'completo', 'Sem checklist completo nos últimos 7 dias.');
        }

        foreach ($v->pneusMontados()->with('componente.inspecoes')->get() as $i) {
            foreach ($i->componente?->alertas() ?? [] as $a) {
                $add('pneu', $a['gravidade'], $i->componente->id.$a['mensagem'], 'Pneu '.$i->componente->numero_fogo.' ('.$i->posicao.'): '.$a['mensagem']);
            }
        }
        foreach ($v->bateriasMontadas()->with('componente.testes')->get() as $i) {
            foreach ($i->componente?->alertas() ?? [] as $a) {
                $add('bateria', $a['gravidade'], $i->componente->id.$a['mensagem'], 'Bateria '.$i->componente->rotulo().': '.$a['mensagem']);
            }
        }

        if ($saida = $v->saidaAberta()) {
            $horas = intdiv(max(0, now()->timestamp - $saida->saida_em->timestamp), 3600);
            if ($horas >= FrotaSaidaVeiculo::AVISO_FORA_HORAS) {
                $add('saida', self::ATENCAO, 'fora', 'Veículo fora há '.($horas >= 48 ? intdiv($horas, 24).' dias' : $horas.' horas').' ('.FrotaSaidaVeiculo::finalidadeLabels()[$saida->finalidade].', com '.$saida->condutor().').');
            }
        }

        foreach (FrotaMulta::emAberto()->where('ativo_id', $v->id)->with('motorista')->get() as $m) {
            $dias = (int) now()->startOfDay()->diffInDays($m->vencimento->copy()->startOfDay(), false);
            $rotulo = 'Multa '.$m->numero_auto.' (R$ '.number_format((float) $m->valor, 2, ',', '.').')';
            if ($dias < 0) {
                $add('multa', self::CRITICA, $m->id.'venc', "{$rotulo} vencida há ".abs($dias).' dia(s).');
            } elseif ($dias <= FrotaMulta::AVISO_VENCIMENTO_DIAS) {
                $add('multa', self::ATENCAO, $m->id.'venc', "{$rotulo} vence em {$dias} dia(s).");
            }
            if (! $m->motorista_id) {
                $prazo = $m->prazo_indicacao && $m->prazo_indicacao->lt(now()->startOfDay());
                $add('multa', $prazo ? self::CRITICA : self::ATENCAO, $m->id.'cond', "{$rotulo}: condutor não indicado".($m->prazo_indicacao ? ' (prazo '.$m->prazo_indicacao->format('d/m/Y').')' : '').'.');
            }
        }

        foreach (FrotaSinistro::where('ativo_id', $v->id)->whereNotIn('situacao', [FrotaSinistro::ENCERRADO, FrotaSinistro::CANCELADO])->get() as $s) {
            $rotulo = FrotaSinistro::tipoLabels()[$s->tipo].' de '.$s->ocorrido_em->format('d/m/Y');
            if ($s->parado()) {
                $dias = (int) $s->diasParado();
                $add('sinistro', $dias >= FrotaSinistro::PARADO_CRITICO_DIAS ? self::CRITICA : self::ATENCAO, $s->id.'parado', "{$rotulo}: veículo parado há {$dias} dia(s).");
            }
            if (($s->tipo === 'furto_roubo' || $s->houve_vitima) && blank($s->bo_numero)) {
                $add('sinistro', self::ATENCAO, $s->id.'bo', "{$rotulo}: falta o número do B.O.");
            }
            if (! $s->valor_orcamento && $s->ocorrido_em->lt(now()->subDays(FrotaSinistro::SEM_ORCAMENTO_DIAS))) {
                $add('sinistro', self::ATENCAO, $s->id.'orc', "{$rotulo}: sem orçamento há mais de ".FrotaSinistro::SEM_ORCAMENTO_DIAS.' dias.');
            }
        }

        $ultimoConsumo = FrotaAbastecimento::where('ativo_id', $v->id)->whereNotNull('consumo_km_l')->latest('abastecido_em')->first();
        if ($ultimoConsumo && $ultimoConsumo->abastecido_em->gte(now()->subDays(30))) {
            $d = AbastecimentoService::desvio($ultimoConsumo);
            if (in_array($d['situacao'], ['atencao', 'critica'], true)) {
                $add('consumo', $d['situacao'] === 'critica' ? self::CRITICA : self::ATENCAO, $ultimoConsumo->id, 'Consumo de '.number_format((float) $ultimoConsumo->consumo_km_l, 2, ',', '.').' km/l em '.$ultimoConsumo->abastecido_em->format('d/m')
                    .', abaixo do habitual ('.number_format($d['media'], 2, ',', '.').' km/l). Verifique o veículo.');
            }
        }

        foreach (FrotaPlanoRevisao::where('ativo_id', $v->id)->where('ativo', true)->get() as $plano) {
            $r = RevisaoService::situacao($plano);
            if (in_array($r['situacao'], [RevisaoService::VENCIDA, RevisaoService::PROXIMA], true)) {
                $add('revisao', $r['situacao'] === RevisaoService::VENCIDA ? self::CRITICA : self::ATENCAO, $plano->id, $r['mensagem']);
            }
        }

        $oleo = OleoStatus::para($v);
        if (in_array($oleo['situacao'], [OleoStatus::VENCIDA, OleoStatus::PROXIMA], true)) {
            $add('oleo', $oleo['situacao'] === OleoStatus::VENCIDA ? self::CRITICA : self::ATENCAO, 'troca', $oleo['mensagem']);
        }
        if ($c = OleoStatus::consumoAnormal($v)) {
            $add('oleo', self::ATENCAO, 'consumo', "Consumo anormal de óleo: {$c['litros']} L repostos em {$c['km']} km desde a última troca.");
        }

        foreach (['licenciamento' => 'Licenciamento', 'ipva' => 'IPVA', 'seguro' => 'Seguro', 'tacografo' => 'Tacógrafo'] as $campo => $nome) {
            $data = $v->{$campo.'_vencimento'};
            if (! $data) {
                continue;
            }
            $dias = (int) now()->startOfDay()->diffInDays($data->copy()->startOfDay(), false);
            if ($dias < 0) {
                $add('documento', self::CRITICA, $campo, "{$nome} vencido há ".abs($dias).' dia(s) ('.$data->format('d/m/Y').').');
            } elseif ($dias <= self::AVISO_DOCUMENTO_DIAS) {
                $add('documento', self::ATENCAO, $campo, "{$nome} vence em {$dias} dia(s) (".$data->format('d/m/Y').').');
            }
        }

        return $p;
    }

    /** CNH vencida/vencendo (30 dias) e pontos do motorista nos últimos 12 meses. */
    public function motoristas(): Collection
    {
        $p = collect();
        foreach (FleetDriver::query()->where('active', true)->get() as $m) {
            $add = fn (string $gravidade, string $detalhe, string $mensagem) => $p->push([
                'chave' => md5('cnh|'.$m->id.'|'.$detalhe), 'categoria' => 'cnh', 'gravidade' => $gravidade, 'ativo_id' => null,
                'placa' => null, 'veiculo' => null, 'mensagem' => $m->name.': '.$mensagem,
            ]);
            if ($m->cnh_expiry_date) {
                $dias = (int) now()->startOfDay()->diffInDays($m->cnh_expiry_date->copy()->startOfDay(), false);
                if ($dias < 0) {
                    $add(self::CRITICA, 'cnh', 'CNH vencida há '.abs($dias).' dia(s) ('.$m->cnh_expiry_date->format('d/m/Y').'). Não pode conduzir.');
                } elseif ($dias <= self::AVISO_DOCUMENTO_DIAS) {
                    $add(self::ATENCAO, 'cnh', "CNH vence em {$dias} dia(s) (".$m->cnh_expiry_date->format('d/m/Y').').');
                }
            }
            $pontos = MultaService::pontosDoMotorista($m);
            if ($pontos >= FrotaMulta::PONTOS_CRITICO) {
                $add(self::CRITICA, 'pontos', "{$pontos} pontos na CNH nos últimos 12 meses (risco de suspensão).");
            } elseif ($pontos >= FrotaMulta::PONTOS_ATENCAO) {
                $add(self::ATENCAO, 'pontos', "{$pontos} pontos na CNH nos últimos 12 meses.");
            }
        }

        return $p;
    }

    /** Itens das categorias da frota com saldo total abaixo do mínimo. */
    public function estoqueBaixo(): Collection
    {
        $categorias = PartCategory::query()->whereIn('name', array_keys(CatalogoEstoqueFrota::ITENS))->pluck('id');
        if ($categorias->isEmpty()) {
            return collect();
        }

        return Part::query()->active()->whereIn('part_category_id', $categorias)->where('minimum_stock', '>', 0)->withSum('stocks as saldo', 'current_quantity')->get()
            ->filter(fn (Part $p) => (float) $p->saldo < (float) $p->minimum_stock)
            ->map(fn (Part $p) => [
                'chave' => md5('estoque|'.$p->id), 'categoria' => 'estoque', 'ativo_id' => null, 'placa' => null, 'veiculo' => null,
                'gravidade' => (float) $p->saldo <= 0 ? self::CRITICA : self::ATENCAO,
                'mensagem' => $p->name.' abaixo do mínimo: saldo '.(float) $p->saldo.' '.$p->unit_of_measure.', mínimo '.(float) $p->minimum_stock.'.',
            ])->values();
    }

    /** Só pendência de manutenção do veículo pode virar OS. */
    public function permiteOs(array $pendencia): bool
    {
        return filled($pendencia['ativo_id'] ?? null) && ! in_array($pendencia['categoria'], ['multa', 'saida', 'cnh', 'estoque', 'sinistro', 'consumo'], true);
    }

    /** OS aberta para esta pendência, se já existir (a descrição leva a chave). */
    public function osAberta(string $chave): ?MaintenanceOrder
    {
        return MaintenanceOrder::query()->where('description', 'like', "[Frota:{$chave}]%")
            ->whereNotIn('status', ['Concluída', 'Completado', 'Cancelada', 'Cancelado'])->first();
    }

    /**
     * Abre uma OS corretiva para a pendência (uma só por pendência enquanto estiver aberta).
     *
     * @param  array<string, mixed>  $pendencia
     *
     * @throws ValidationException
     */
    public function gerarOs(array $pendencia): MaintenanceOrder
    {
        if (! $this->permiteOs($pendencia)) {
            throw ValidationException::withMessages(['os' => 'Este tipo de pendência (multa, saída, CNH ou estoque) não gera OS de manutenção.']);
        }
        $ativo = $pendencia['ativo_id'] ? Asset::find($pendencia['ativo_id']) : null;
        if (! $ativo) {
            throw ValidationException::withMessages(['os' => 'Esta pendência não é de um veículo; use a compra/solicitação de material.']);
        }
        if ($existente = $this->osAberta($pendencia['chave'])) {
            throw ValidationException::withMessages(['os' => 'Já existe a OS '.$existente->os_number.' aberta para esta pendência.']);
        }

        return MaintenanceOrder::create([
            'tenant_id' => $ativo->tenant_id,
            'asset_id' => $ativo->id,
            'client_id' => $ativo->client_id,
            'maintenance_type' => in_array($pendencia['categoria'], ['oleo', 'revisao'], true) ? MaintenanceOrder::TYPE_PREVENTIVE : MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => "[Frota:{$pendencia['chave']}] ".$pendencia['mensagem'],
        ]);
    }
}
