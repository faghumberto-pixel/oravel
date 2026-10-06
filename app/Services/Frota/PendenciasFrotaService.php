<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaSaidaVeiculo;
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
        return ['checklist' => 'Checklist', 'pneu' => 'Pneus', 'bateria' => 'Baterias', 'oleo' => 'Óleo', 'saida' => 'Entrada e saída', 'documento' => 'Documentos', 'estoque' => 'Estoque'];
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
            $lista = $lista->concat($this->estoqueBaixo());
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
            $horas = (int) $saida->saida_em->diffInHours(now());
            if ($horas >= FrotaSaidaVeiculo::AVISO_FORA_HORAS) {
                $add('saida', self::ATENCAO, 'fora', 'Veículo fora há '.($horas >= 48 ? intdiv($horas, 24).' dias' : $horas.' horas').' ('.FrotaSaidaVeiculo::finalidadeLabels()[$saida->finalidade].', com '.$saida->condutor().').');
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
            'maintenance_type' => $pendencia['categoria'] === 'oleo' ? MaintenanceOrder::TYPE_PREVENTIVE : MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => "[Frota:{$pendencia['chave']}] ".$pendencia['mensagem'],
        ]);
    }
}
