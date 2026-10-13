<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaChecklist;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPercurso;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaSinistro;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Entrada e saída de veículos: quem levou, para quê, km, e as travas (checklist bloqueado, já fora, CNH vencida). */
class SaidaVeiculoService
{
    /**
     * @param  array{finalidade: string, motivo: string, saida_em?: ?string, motorista_id: string, destino?: ?string, cliente_id?: ?string, ordem_servico_id?: ?string, odometro: int|string, combustivel?: ?string, observacoes?: ?string, justificativa_odometro?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrarSaida(Asset $ativo, array $dados, ?User $usuario = null): FrotaSaidaVeiculo
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm entrada e saída registradas aqui.');
        }
        if ($ativo->baixaVigente()) {
            $this->erro('ativo', 'Este veículo foi baixado e não pode registrar saída.');
        }
        if (! array_key_exists($dados['finalidade'] ?? '', FrotaSaidaVeiculo::finalidadeLabels())) {
            $this->erro('finalidade', 'Escolha a finalidade da saída.');
        }
        [$origemTipo, $origemClienteId, $origemTexto] = $this->local($dados, 'origem', 'empresa');
        [$destinoTipo, $destinoClienteId, $destinoTexto] = $this->local($dados, 'destino', 'outro', 'cliente_id');
        $percurso = $this->percursoDoTrecho($dados, [$origemTipo, $origemClienteId, $origemTexto], [$destinoTipo, $destinoClienteId, $destinoTexto]);
        if (blank(trim((string) ($dados['motivo'] ?? '')))) {
            $this->erro('motivo', 'Informe o motivo da saída.');
        }
        $saidaEm = $this->dataHora($dados['saida_em'] ?? null, 'saida_em');
        if (blank($dados['motorista_id'] ?? null)) {
            $this->erro('motorista_id', 'Escolha o motorista (cadastrado em Logística → Frota → Motoristas).');
        }
        if ($fora = FrotaSaidaVeiculo::fora()->where('ativo_id', $ativo->id)->first()) {
            $this->erro('ativo', 'Este veículo já está fora desde '.$fora->saida_em->format('d/m H:i').' (com '.$fora->condutor().'). Registre a entrada antes.');
        }
        if ($parado = SinistroService::paradoPorSinistro($ativo)) {
            $this->erro('ativo', 'Veículo parado por sinistro ('.FrotaSinistro::tipoLabels()[$parado->tipo].' em '.$parado->ocorrido_em->format('d/m/Y').'). Registre "voltou a rodar" antes de sair.');
        }
        if ($bloqueio = $ativo->bloqueioChecklistFrota()) {
            $this->erro('ativo', 'Veículo bloqueado pelo checklist desde '.$bloqueio->concluido_em?->format('d/m H:i').'. Um responsável precisa liberá-lo antes da saída.');
        }
        $motorista = filled($dados['motorista_id'] ?? null) ? FleetDriver::find($dados['motorista_id']) : null;
        if (filled($dados['motorista_id'] ?? null) && ! $motorista) {
            $this->erro('motorista_id', 'Motorista não encontrado.');
        }
        if ($motorista?->cnh_expiry_date && $motorista->cnh_expiry_date->lt(now()->startOfDay())) {
            $this->erro('motorista_id', 'A CNH de '.$motorista->name.' venceu em '.$motorista->cnh_expiry_date->format('d/m/Y').'. Não pode conduzir o veículo.');
        }

        // Equipamento levado (opcional): tem que existir, não ser o próprio veículo e não estar em outra saída aberta.
        $equipamento = null;
        if (filled($dados['equipamento_id'] ?? null)) {
            $equipamento = Asset::query()->find($dados['equipamento_id']);
            if (! $equipamento) {
                $this->erro('equipamento_id', 'Equipamento não encontrado.');
            }
            if ($equipamento->id === $ativo->id) {
                $this->erro('equipamento_id', 'O veículo não pode levar a si mesmo.');
            }
            if ($levando = FrotaSaidaVeiculo::fora()->where('equipamento_id', $equipamento->id)->with('veiculo')->first()) {
                $this->erro('equipamento_id', 'O equipamento '.($equipamento->patrimonio ?? $equipamento->name).' já está sendo levado pelo veículo '.($levando->veiculo?->placa ?? $levando->veiculo?->name).' desde '.$levando->saida_em->format('d/m H:i').'.');
            }
        }

        return DB::transaction(function () use ($ativo, $dados, $usuario, $motorista, $saidaEm, $equipamento, $origemTipo, $origemClienteId, $origemTexto, $destinoTipo, $destinoClienteId, $destinoTexto, $percurso) {
            $odometro = (int) $dados['odometro'];

            $saida = FrotaSaidaVeiculo::create([
                'tenant_id' => $ativo->tenant_id,
                'ativo_id' => $ativo->id,
                'equipamento_id' => $equipamento?->id,
                'motorista_id' => $motorista?->id,
                'condutor_nome' => null,
                'finalidade' => $dados['finalidade'],
                'origem_tipo' => $origemTipo,
                'origem_cliente_id' => $origemClienteId,
                'origem' => $origemTexto,
                'destino_tipo' => $destinoTipo,
                'percurso_id' => $percurso?->id,
                'destino' => $destinoTexto,
                'motivo' => trim($dados['motivo']),
                'cliente_id' => $destinoTipo === 'cliente' ? $destinoClienteId : ($dados['cliente_id'] ?? null),
                'ordem_servico_id' => $dados['ordem_servico_id'] ?? null,
                'saida_em' => $saidaEm,
                'odometro_saida' => $odometro,
                'combustivel_saida' => $dados['combustivel'] ?? null,
                'checklist_saida_id' => $this->ultimoChecklist($ativo, FrotaChecklist::TIPO_SAIDA)?->id,
                'observacoes_saida' => $dados['observacoes'] ?? null,
                'registrado_por' => $usuario?->id ?? auth()->id(),
            ]);
            // Recusa (km menor sem justificativa) desfaz a saída inteira.
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'saida_veiculo', $saida->id, $dados['justificativa_odometro'] ?? null, $usuario?->id, $saidaEm);

            return $saida;
        });
    }

    /**
     * Local (origem ou destino) do trecho: empresa, cliente ou outro. Sem tipo informado vale o padrão da chamada
     * (compatível com quem só manda o texto do destino). Devolve [tipo, cliente_id, texto para exibir].
     *
     * @return array{0: string, 1: ?string, 2: string}
     */
    private function local(array $dados, string $campo, string $tipoPadrao, ?string $campoCliente = null): array
    {
        $tipo = $dados[$campo.'_tipo'] ?? (filled($dados[$campo] ?? null) || $campo === 'origem' ? $tipoPadrao : null);
        $campoCliente ??= $campo.'_cliente_id';
        $rotulo = $campo === 'origem' ? 'a origem' : 'o destino';

        if (! array_key_exists((string) $tipo, FrotaPercurso::TIPOS)) {
            $this->erro($campo, 'Informe '.$rotulo.'.');
        }

        if ($tipo === 'empresa') {
            return ['empresa', null, 'Empresa'];
        }

        if ($tipo === 'cliente') {
            $cliente = filled($dados[$campoCliente] ?? null) ? Client::query()->find($dados[$campoCliente]) : null;
            if (! $cliente) {
                $this->erro($campo, 'Escolha o cliente d'.($campo === 'origem' ? 'a origem' : 'o destino').'.');
            }

            return ['cliente', $cliente->id, $cliente->name];
        }

        $texto = trim((string) ($dados[$campo] ?? ''));
        if ($texto === '') {
            $this->erro($campo, 'Informe '.$rotulo.'.');
        }

        return ['outro', null, $texto];
    }

    /** Percurso escolhido (tem que ligar origem e destino) ou o único percurso ativo que liga os dois locais. */
    private function percursoDoTrecho(array $dados, array $origem, array $destino): ?FrotaPercurso
    {
        $liga = fn (FrotaPercurso $p) => $p->liga($origem[0], $origem[1], $origem[0] === 'outro' ? $origem[2] : null, $destino[0], $destino[1], $destino[0] === 'outro' ? $destino[2] : null);

        if (filled($dados['percurso_id'] ?? null)) {
            $p = FrotaPercurso::query()->find($dados['percurso_id']);
            if (! $p || ! $p->ativo) {
                $this->erro('percurso_id', 'Percurso não encontrado ou inativo.');
            }
            if (! $liga($p)) {
                $this->erro('percurso_id', 'O percurso "'.$p->nome.'" não liga '.$origem[2].' a '.$destino[2].'.');
            }

            return $p;
        }

        $candidatos = FrotaPercurso::query()->where('ativo', true)->get()->filter($liga)->values();

        return $candidatos->count() === 1 ? $candidatos->first() : null;
    }

    /**
     * @param  array{retorno_em?: ?string, odometro: int|string, combustivel?: ?string, observacoes?: ?string, justificativa_odometro?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrarEntrada(FrotaSaidaVeiculo $saida, array $dados, ?User $usuario = null): FrotaSaidaVeiculo
    {
        if (! $saida->estaFora()) {
            $this->erro('saida', 'Este veículo já teve a entrada registrada.');
        }
        $retornoEm = $this->dataHora($dados['retorno_em'] ?? null, 'retorno_em');
        if ($retornoEm->lt($saida->saida_em)) {
            $this->erro('retorno_em', 'A entrada não pode ser antes da saída ('.$saida->saida_em->format('d/m/Y H:i').').');
        }
        $odometro = (int) $dados['odometro'];
        if ($odometro < $saida->odometro_saida && blank($dados['justificativa_odometro'] ?? null)) {
            $this->erro('odometro', "O odômetro da entrada ({$odometro} km) é menor que o da saída ({$saida->odometro_saida} km). Confira o valor ou informe a justificativa.");
        }

        return DB::transaction(function () use ($saida, $dados, $usuario, $odometro, $retornoEm) {
            $ativo = $saida->veiculo;
            FrotaLeituraOdometro::registrar($ativo, $odometro, 'entrada_veiculo', $saida->id, $dados['justificativa_odometro'] ?? null, $usuario?->id, $retornoEm);

            $saida->update([
                'retorno_em' => $retornoEm,
                'odometro_retorno' => $odometro,
                'combustivel_retorno' => $dados['combustivel'] ?? null,
                'checklist_retorno_id' => $this->ultimoChecklist($ativo, FrotaChecklist::TIPO_RETORNO, $saida->saida_em)?->id,
                'observacoes_retorno' => $dados['observacoes'] ?? null,
                'retorno_registrado_por' => $usuario?->id ?? auth()->id(),
            ]);

            return $saida;
        });
    }

    /** Checklist concluído do tipo pedido (para o retorno, só os feitos depois da saída). */
    private function ultimoChecklist(Asset $ativo, string $tipo, ?\DateTimeInterface $depoisDe = null): ?FrotaChecklist
    {
        return FrotaChecklist::where('ativo_id', $ativo->id)->where('tipo', $tipo)->whereNotNull('concluido_em')
            ->when($depoisDe, fn ($q) => $q->where('concluido_em', '>=', $depoisDe))
            ->when(! $depoisDe, fn ($q) => $q->where('concluido_em', '>=', now()->subHours(12)))
            ->latest('concluido_em')->first();
    }

    /** Data e hora informadas (padrão: agora); não aceita data no futuro. */
    private function dataHora(mixed $valor, string $campo): Carbon
    {
        $momento = blank($valor) ? now() : Carbon::parse($valor);
        if ($momento->gt(now()->addMinutes(5))) {
            $this->erro($campo, 'A data e hora não podem estar no futuro.');
        }

        return $momento;
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
