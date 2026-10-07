<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaPedagio;
use App\Models\FrotaTag;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Tags de pedágio (uma ativa por veículo) e passagens em pedágio (com condutor sugerido pela saída do veículo). */
class PedagioService
{
    /** @throws ValidationException */
    public function criarTag(Asset $ativo, string $numero, string $operadora, ?string $observacoes = null): FrotaTag
    {
        $this->exigirVeiculo($ativo);
        $numero = trim($numero);
        if ($numero === '') {
            $this->erro('numero', 'Informe o número da tag.');
        }
        if (! array_key_exists($operadora, FrotaTag::operadoraLabels())) {
            $this->erro('operadora', 'Escolha a operadora.');
        }
        if (FrotaTag::whereRaw('lower(numero) = ?', [mb_strtolower($numero)])->exists()) {
            $this->erro('numero', 'Já existe uma tag com este número.');
        }
        if (FrotaTag::where('ativo_id', $ativo->id)->where('ativa', true)->exists()) {
            $this->erro('ativo', 'Este veículo já tem uma tag ativa. Cancele a atual antes de cadastrar outra.');
        }

        return FrotaTag::create(['tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'numero' => $numero, 'operadora' => $operadora, 'observacoes' => $observacoes]);
    }

    public function cancelarTag(FrotaTag $tag): FrotaTag
    {
        $tag->update(['ativa' => false]);

        return $tag;
    }

    /**
     * @param  array{passou_em?: ?string, local: string, valor: float|int|string, motorista_id?: ?string, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $dados, ?User $usuario = null): FrotaPedagio
    {
        $this->exigirVeiculo($ativo);
        if (blank(trim((string) ($dados['local'] ?? '')))) {
            $this->erro('local', 'Informe o pedágio (praça ou rodovia).');
        }
        $valor = (float) str_replace(',', '.', (string) ($dados['valor'] ?? 0));
        if ($valor <= 0) {
            $this->erro('valor', 'Informe o valor do pedágio.');
        }
        $quando = blank($dados['passou_em'] ?? null) ? now() : Carbon::parse($dados['passou_em']);
        if ($quando->gt(now()->addMinutes(5))) {
            $this->erro('passou_em', 'A data e hora não podem estar no futuro.');
        }
        $saida = app(MultaService::class)->saidaDaInfracao($ativo, $quando);

        return FrotaPedagio::create([
            'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id,
            'tag_id' => FrotaTag::where('ativo_id', $ativo->id)->where('ativa', true)->value('id'),
            'motorista_id' => ($dados['motorista_id'] ?? null) ?: $saida?->motorista_id, 'saida_veiculo_id' => $saida?->id,
            'passou_em' => $quando, 'local' => trim($dados['local']), 'valor' => $valor, 'observacoes' => $dados['observacoes'] ?? null,
            'registrado_por' => $usuario?->id ?? auth()->id(),
        ]);
    }

    private function exigirVeiculo(Asset $ativo): void
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'Só veículos têm tag e pedágio registrados aqui.');
        }
        if ($ativo->baixaVigente()) {
            $this->erro('ativo', 'Este veículo foi baixado.');
        }
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
