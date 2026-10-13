<?php

namespace App\Support\Colaborador;

use App\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Vencimento das documentações dos veículos (seguro, IPVA, licenciamento e, nos pesados, tacógrafo). Mesmos campos de "Vencimento de veículos". */
class VencimentosVeiculos
{
    public const DIAS_ATENCAO = 30;

    private const DOCUMENTOS = [
        'seguro_vencimento' => 'Seguro',
        'ipva_vencimento' => 'IPVA',
        'licenciamento_vencimento' => 'Licenciamento',
        'tacografo_vencimento' => 'Tacógrafo',
    ];

    /**
     * @return Collection<int, array{ativo: Asset, docs: array<int, array{nome: string, data: Carbon, dias: int, situacao: string}>, pior: string, proximo: ?Carbon}>
     */
    public static function levantar(?string $busca = null): Collection
    {
        $veiculos = $busca && mb_strlen(trim($busca)) >= 2
            ? Asset::search($busca)->filter(fn (Asset $a) => $a->isVehicle())
            : Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->naoBaixado()->get();

        return $veiculos->map(function (Asset $a) {
            $docs = [];
            foreach (self::DOCUMENTOS as $campo => $nome) {
                if ($campo === 'tacografo_vencimento' && ! $a->veiculo_pesado) {
                    continue; // tacógrafo só vale para veículo pesado
                }
                if (! $a->{$campo}) {
                    continue;
                }
                $data = $a->{$campo}->copy()->startOfDay();
                $dias = (int) now()->startOfDay()->diffInDays($data, false);
                $docs[] = ['nome' => $nome, 'data' => $data, 'dias' => $dias, 'situacao' => $dias < 0 ? 'vencido' : ($dias <= self::DIAS_ATENCAO ? 'atencao' : 'ok')];
            }
            usort($docs, fn ($x, $y) => $x['dias'] <=> $y['dias']);
            $pior = collect($docs)->contains('situacao', 'vencido') ? 'vencido' : (collect($docs)->contains('situacao', 'atencao') ? 'atencao' : 'ok');

            return ['ativo' => $a, 'docs' => $docs, 'pior' => $pior, 'proximo' => $docs[0]['data'] ?? null];
        })->sortBy(fn ($v) => $v['docs'] ? $v['docs'][0]['dias'] : PHP_INT_MAX)->values();
    }

    /** @return array{vencidos: int, atencao: int} quantidade de veículos com documento vencido / perto de vencer */
    public static function resumo(): array
    {
        $todos = self::levantar();

        return ['vencidos' => $todos->where('pior', 'vencido')->count(), 'atencao' => $todos->where('pior', 'atencao')->count()];
    }
}
