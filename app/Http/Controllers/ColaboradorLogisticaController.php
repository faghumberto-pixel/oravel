<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaVinculoMotorista;
use App\Services\Frota\KmRodadoService;
use App\Support\Colaborador\Modulos;
use App\Support\Colaborador\VencimentosVeiculos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Área de Logística do app do colaborador: início com as funções e a consulta de vencimentos. */
class ColaboradorLogisticaController extends Controller
{
    public function hub(Request $request): View
    {
        $pode = fn (string $modelo) => Gate::allows('viewAny', $modelo);
        $resumo = $pode(Asset::class) ? VencimentosVeiculos::resumo() : ['vencidos' => 0, 'atencao' => 0];

        $libera = fn (string $recurso) => Modulos::acesso($request->user(), 'logistica', $recurso);

        $funcoes = array_values(array_filter([
            $pode(FrotaSaidaVeiculo::class) && $libera('saida') ? ['rota' => 'colab.logistica.saida', 'icone' => '🚚', 'titulo' => 'Entrada e saída de veículos', 'texto' => 'Motivo, destino e equipamento levado', 'selo' => FrotaSaidaVeiculo::fora()->count(), 'selo_texto' => 'fora'] : null,
            $pode(FrotaAbastecimento::class) && $libera('abastecimento') ? ['rota' => 'colab.logistica.abastecimento', 'icone' => '⛽', 'titulo' => 'Abastecimento', 'texto' => 'Litros, valor e km do veículo', 'selo' => 0, 'selo_texto' => ''] : null,
            $pode(FrotaSaidaVeiculo::class) && $libera('km') ? ['rota' => 'colab.logistica.km', 'icone' => '🔢', 'titulo' => 'Marcar km', 'texto' => 'Leitura do painel do veículo', 'selo' => 0, 'selo_texto' => ''] : null,
            $pode(FrotaVinculoMotorista::class) && $libera('motorista') ? ['rota' => 'colab.logistica.motorista', 'icone' => '🧑‍✈️', 'titulo' => 'Motorista do veículo', 'texto' => 'Incluir ou substituir o titular', 'selo' => 0, 'selo_texto' => ''] : null,
            $pode(FrotaSaidaVeiculo::class) && $libera('km-rodado') ? ['rota' => 'colab.logistica.km-rodado', 'icone' => '🗺️', 'titulo' => 'Km rodado e percursos', 'texto' => 'Total por veículo e por percurso', 'selo' => 0, 'selo_texto' => ''] : null,
            $pode(Asset::class) && $libera('vencimentos') ? ['rota' => 'colab.logistica.vencimentos', 'icone' => '📅', 'titulo' => 'Vencimentos da documentação', 'texto' => 'Seguro, IPVA, licenciamento e tacógrafo', 'selo' => $resumo['vencidos'] + $resumo['atencao'], 'selo_texto' => 'atenção'] : null,
        ]));

        return view('colaborador.logistica', ['funcoes' => $funcoes, 'resumo' => $resumo]);
    }

    /** Km rodado: total por veículo (histórico de odômetro) e o que cada percurso recebeu das viagens. */
    public function kmRodado(Request $request, KmRodadoService $servico): View
    {
        abort_unless(Gate::allows('viewAny', FrotaSaidaVeiculo::class), 403);

        $chave = array_key_exists((string) $request->query('periodo'), KmRodadoService::periodos()) ? $request->query('periodo') : 'mes';
        [$de, $ate, $rotulo] = KmRodadoService::periodo($chave);

        return view('colaborador.km-rodado', [
            'chave' => $chave, 'rotulo' => $rotulo, 'periodos' => KmRodadoService::periodos(),
            'veiculos' => $servico->frota($de, $ate), 'percursos' => $servico->porPercurso($de, $ate),
        ]);
    }

    public function vencimentos(Request $request): View
    {
        abort_unless(Gate::allows('viewAny', Asset::class), 403);

        $aba = $request->query('aba') === 'todos' ? 'todos' : 'atencao';
        $busca = trim((string) $request->query('q'));
        $todos = VencimentosVeiculos::levantar($busca ?: null);
        $lista = $aba === 'todos' ? $todos : $todos->filter(fn ($v) => in_array($v['pior'], ['vencido', 'atencao'], true))->values();

        return view('colaborador.vencimentos', ['lista' => $lista, 'aba' => $aba, 'busca' => $busca, 'total' => $todos->count(), 'atencao' => $todos->whereIn('pior', ['vencido', 'atencao'])->count()]);
    }
}
