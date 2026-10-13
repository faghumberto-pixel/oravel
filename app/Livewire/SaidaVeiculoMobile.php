<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaPercurso;
use App\Models\FrotaSaidaVeiculo;
use App\Services\Frota\SaidaVeiculoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Entrada e saída de veículos pelo celular (Logística): lista os veículos, registra a saída e a volta. */
#[Layout('layouts.logistica-mobile')]
class SaidaVeiculoMobile extends Component
{
    /** Veículo escolhido (null = lista). */
    public ?string $ativoId = null;

    public string $finalidade = 'visita_tecnica';

    public ?string $motoristaId = null;

    /** Equipamento que o veículo vai levar nesta saída (opcional) e o texto da busca dele. */
    public ?string $equipamentoId = null;

    public string $buscaEquipamento = '';

    /** Aberto pelo app do colaborador (outro endereço): o "Início" volta para o app, não para o painel. */
    public bool $modoApp = false;

    /** Trecho: de onde sai e para onde vai (empresa, cliente ou outro local) + percurso opcional. */
    public string $origemTipo = 'empresa';

    public ?string $origemClienteId = null;

    public string $origemTexto = '';

    public string $destinoTipo = 'cliente';

    public ?string $destinoClienteId = null;

    public string $destino = '';

    public ?string $percursoId = null;

    public string $motivo = '';

    public string $dataHora = '';

    public string $odometro = '';

    public string $combustivel = '';

    public string $observacoes = '';

    public string $justificativaOdometro = '';

    public ?string $mensagem = null;

    public ?string $erro = null;

    public function mount(?string $assetId = null): void
    {
        $this->modoApp = (bool) request()->attributes->get('colab');
        Gate::authorize('viewAny', FrotaSaidaVeiculo::class);
        if ($assetId) {
            $this->escolher($assetId);
        }
    }

    public function escolher(string $id): void
    {
        $ativo = Asset::findOrFail($id);
        abort_unless($ativo->isVehicle(), 404, 'Este ativo não é um veículo.');
        $this->ativoId = $ativo->id;
        $this->reiniciarCampos();
        $this->odometro = (string) (int) floor((float) $ativo->odometro_atual);

        // O veículo sai de onde está: da empresa, ou do cliente onde chegou no último trecho.
        $local = $ativo->localAtual();
        $this->origemTipo = $local['tipo'];
        $this->origemClienteId = $local['cliente_id'];
        $this->origemTexto = (string) $local['texto'];
        $this->destinoTipo = $local['tipo'] === 'empresa' ? 'cliente' : 'empresa';
    }

    public function escolherEquipamento(string $id): void
    {
        $this->equipamentoId = $id;
        $this->buscaEquipamento = '';
    }

    public function limparEquipamento(): void
    {
        $this->equipamentoId = null;
        $this->buscaEquipamento = '';
    }

    public function voltar(): void
    {
        $this->ativoId = null;
        $this->erro = null;
    }

    private function reiniciarCampos(): void
    {
        $this->reset(['finalidade', 'motoristaId', 'equipamentoId', 'buscaEquipamento', 'destinoClienteId', 'destino', 'percursoId', 'origemTexto', 'motivo', 'combustivel', 'observacoes', 'justificativaOdometro', 'erro']);
        $this->dataHora = now()->format('Y-m-d\TH:i');
    }

    public function saida(): void
    {
        Gate::authorize('create', FrotaSaidaVeiculo::class);
        $this->executar(function (Asset $ativo) {
            app(SaidaVeiculoService::class)->registrarSaida($ativo, [
                'finalidade' => $this->finalidade, 'motorista_id' => $this->motoristaId ?: null, 'equipamento_id' => $this->equipamentoId ?: null,
                'origem_tipo' => $this->origemTipo, 'origem_cliente_id' => $this->origemClienteId ?: null, 'origem' => $this->origemTexto,
                'destino_tipo' => $this->destinoTipo, 'cliente_id' => $this->destinoClienteId ?: null, 'percurso_id' => $this->percursoId ?: null,
                'destino' => $this->destino, 'motivo' => $this->motivo, 'saida_em' => $this->dataHora ?: null, 'odometro' => (int) $this->odometro,
                'combustivel' => $this->combustivel ?: null, 'observacoes' => $this->observacoes, 'justificativa_odometro' => $this->justificativaOdometro ?: null,
            ], auth()->user());

            return 'Saída registrada.';
        });
    }

    public function entrada(): void
    {
        Gate::authorize('create', FrotaSaidaVeiculo::class);
        $this->executar(function (Asset $ativo) {
            $aberta = $ativo->saidaAberta();
            abort_unless($aberta, 404, 'Este veículo não está fora.');
            $fechada = app(SaidaVeiculoService::class)->registrarEntrada($aberta, [
                'retorno_em' => $this->dataHora ?: null, 'odometro' => (int) $this->odometro, 'combustivel' => $this->combustivel ?: null,
                'observacoes' => $this->observacoes, 'justificativa_odometro' => $this->justificativaOdometro ?: null,
            ], auth()->user());

            $km = $fechada->kmRodado();
            $previsto = (float) $fechada->percurso?->km_previsto;

            return 'Entrada registrada em '.$fechada->destino.'.'
                .($km !== null ? ' Km da viagem: '.number_format($km, 0, ',', '.').' km' : '')
                .($fechada->percurso ? ' ('.$fechada->percurso->nome.($previsto > 0 ? ', previsto '.rtrim(rtrim(number_format($previsto, 1, ',', '.'), '0'), ',').' km' : '').').' : ($km !== null ? '.' : ''));
        });
    }

    private function executar(\Closure $acao): void
    {
        $this->mensagem = $this->erro = null;

        try {
            $ativo = Asset::findOrFail($this->ativoId);
            $this->mensagem = $acao($ativo);
            $this->ativoId = null;
        } catch (ValidationException $e) {
            $this->erro = collect($e->errors())->flatten()->first();
        }
    }

    private function ativoAberta($fora, Asset $ativo): bool
    {
        return $fora->has($ativo->id);
    }

    public function render()
    {
        $ativo = $this->ativoId ? Asset::find($this->ativoId) : null;
        $veiculos = $ativo ? collect() : Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get();
        $fora = FrotaSaidaVeiculo::fora()->with('motorista', 'equipamento')->get()->keyBy('ativo_id');

        // Busca do equipamento levado: por patrimônio, placa, TAG ou nome (nunca o próprio veículo nem outro veículo).
        $equipamentos = $ativo && ! $this->equipamentoId && mb_strlen(trim($this->buscaEquipamento)) >= 2
            ? Asset::search($this->buscaEquipamento)->reject(fn (Asset $a) => $a->isVehicle() || $a->id === $ativo->id)->values()
            : collect();

        // Percursos que ligam a origem ao destino escolhidos (em qualquer sentido): se só houver um, é usado sozinho.
        $percursosCompativeis = $ativo && ! $this->ativoAberta($fora, $ativo) ? FrotaPercurso::query()->where('ativo', true)->orderBy('nome')->get()->filter(
            fn (FrotaPercurso $p) => $p->liga($this->origemTipo, $this->origemClienteId, $this->origemTipo === 'outro' ? $this->origemTexto : null, $this->destinoTipo, $this->destinoClienteId, $this->destinoTipo === 'outro' ? $this->destino : null)
        )->values() : collect();

        // Veículos que chegaram a um local que não é a empresa (último trecho concluído): "no cliente X".
        $noLocal = $ativo ? collect() : FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->whereIn('ativo_id', $veiculos->pluck('id'))
            ->orderByDesc('retorno_em')->get()->unique('ativo_id')->filter(fn (FrotaSaidaVeiculo $s) => $s->destino_tipo !== 'empresa')->keyBy('ativo_id');

        return view('livewire.saida-veiculo-mobile', [
            'noLocal' => $noLocal,
            'clientes' => $ativo ? Client::query()->orderBy('name')->pluck('name', 'id')->all() : [],
            'percursosCompativeis' => $percursosCompativeis,
            'localAtual' => $ativo?->localAtual(),
            'ativo' => $ativo,
            'aberta' => $ativo ? $fora->get($ativo->id) : null,
            'veiculos' => $veiculos,
            'fora' => $fora,
            'equipamentos' => $equipamentos,
            'equipamentoSelecionado' => $this->equipamentoId ? Asset::find($this->equipamentoId) : null,
            'finalidades' => FrotaSaidaVeiculo::finalidadeLabels(),
            'motoristas' => $ativo ? FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
