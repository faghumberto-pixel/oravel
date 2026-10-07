<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaItemSeguranca;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Kit de segurança dos veículos: itens, conferência (presente/ausente, validade) e alertas calculados ao vivo. */
class KitSegurancaService
{
    /** Itens sugeridos: nome, obrigatório, tem validade. Obrigatórios = os que o trânsito exige; o resto é boa prática. */
    public const PADRAO = [
        ['Extintor de incêndio', true, true], ['Triângulo de sinalização', true, false], ['Macaco', true, false], ['Chave de roda', true, false],
        ['Kit de primeiros socorros', false, true], ['Colete refletivo', false, false],
    ];

    /**
     * @param  array{nome: string, identificacao?: ?string, obrigatorio?: bool, tem_validade?: bool, validade?: ?string, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function criar(Asset $ativo, array $dados): FrotaItemSeguranca
    {
        $this->exigirVeiculo($ativo);
        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome === '') {
            $this->erro('nome', 'Informe o item (ex.: Extintor).');
        }
        if (FrotaItemSeguranca::where('ativo_id', $ativo->id)->where('ativo', true)->whereRaw('lower(nome) = ?', [mb_strtolower($nome)])->exists()) {
            $this->erro('nome', 'Este veículo já tem o item "'.$nome.'".');
        }
        $temValidade = (bool) ($dados['tem_validade'] ?? false);
        if ($temValidade && blank($dados['validade'] ?? null)) {
            $this->erro('validade', 'Informe a validade do item.');
        }

        return FrotaItemSeguranca::create([
            'tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'nome' => $nome, 'identificacao' => $dados['identificacao'] ?? null,
            'obrigatorio' => (bool) ($dados['obrigatorio'] ?? true), 'tem_validade' => $temValidade, 'validade' => $temValidade ? $dados['validade'] : null,
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
    }

    /**
     * Cria os itens sugeridos que o veículo ainda não tem. Os que têm validade nascem SEM data: aparecem como pendência até serem conferidos.
     *
     * @return int quantos foram criados
     */
    public function aplicarPadrao(Asset $ativo): int
    {
        $this->exigirVeiculo($ativo);
        $criados = 0;
        foreach (self::PADRAO as [$nome, $obrigatorio, $temValidade]) {
            if (! FrotaItemSeguranca::where('ativo_id', $ativo->id)->where('ativo', true)->whereRaw('lower(nome) = ?', [mb_strtolower($nome)])->exists()) {
                FrotaItemSeguranca::create(['tenant_id' => $ativo->tenant_id, 'ativo_id' => $ativo->id, 'nome' => $nome, 'obrigatorio' => $obrigatorio, 'tem_validade' => $temValidade]);
                $criados++;
            }
        }

        return $criados;
    }

    /**
     * Conferência: marca presente/ausente, atualiza a validade e a data da conferência.
     *
     * @param  array{presente: bool, validade?: ?string, identificacao?: ?string, conferido_em?: ?string, observacoes?: ?string}  $dados
     *
     * @throws ValidationException
     */
    public function conferir(FrotaItemSeguranca $item, array $dados): FrotaItemSeguranca
    {
        if (! $item->ativo) {
            $this->erro('item', 'Este item está desativado.');
        }
        $dia = blank($dados['conferido_em'] ?? null) ? now()->startOfDay() : Carbon::parse($dados['conferido_em'])->startOfDay();
        if ($dia->gt(now()->endOfDay())) {
            $this->erro('conferido_em', 'A data da conferência não pode estar no futuro.');
        }
        $presente = (bool) $dados['presente'];
        if ($presente && $item->tem_validade && blank($dados['validade'] ?? null) && ! $item->validade) {
            $this->erro('validade', 'Informe a validade de "'.$item->nome.'".');
        }

        $item->update([
            'presente' => $presente,
            'validade' => $item->tem_validade && filled($dados['validade'] ?? null) ? $dados['validade'] : $item->validade,
            'identificacao' => $dados['identificacao'] ?? $item->identificacao,
            'conferido_em' => $dia,
            'observacoes' => $dados['observacoes'] ?? $item->observacoes,
        ]);

        return $item;
    }

    public function desativar(FrotaItemSeguranca $item): FrotaItemSeguranca
    {
        $item->update(['ativo' => false]);

        return $item;
    }

    /**
     * Alertas calculados ao vivo de um item.
     *
     * @return array<int, array{gravidade: string, mensagem: string}>
     */
    public static function alertas(FrotaItemSeguranca $i): array
    {
        $a = [];
        if (! $i->presente) {
            $a[] = ['gravidade' => $i->obrigatorio ? 'critica' : 'atencao', 'mensagem' => $i->nome.' ausente'.($i->obrigatorio ? ' (obrigatório)' : '').'.'];

            return $a;   // item ausente: validade e conferência não importam
        }
        if ($i->tem_validade) {
            if (! $i->validade) {
                $a[] = ['gravidade' => 'atencao', 'mensagem' => $i->nome.': validade não informada.'];
            } else {
                $dias = (int) now()->startOfDay()->diffInDays($i->validade->copy()->startOfDay(), false);
                if ($dias < 0) {
                    $a[] = ['gravidade' => 'critica', 'mensagem' => $i->nome.' vencido há '.abs($dias).' dia(s) ('.$i->validade->format('d/m/Y').').'];
                } elseif ($dias <= FrotaItemSeguranca::AVISO_VALIDADE_DIAS) {
                    $a[] = ['gravidade' => 'atencao', 'mensagem' => $i->nome." vence em {$dias} dia(s) (".$i->validade->format('d/m/Y').').'];
                }
            }
        }
        if (! $i->conferido_em || $i->conferido_em->lt(now()->subDays(FrotaItemSeguranca::CONFERENCIA_DIAS)->startOfDay())) {
            $a[] = ['gravidade' => 'atencao', 'mensagem' => $i->nome.': sem conferência há mais de '.FrotaItemSeguranca::CONFERENCIA_DIAS.' dias.'];
        }

        return $a;
    }

    private function exigirVeiculo(Asset $ativo): void
    {
        if (! $ativo->isVehicle()) {
            $this->erro('ativo', 'O kit de segurança só vale para ativos do tipo Veículo.');
        }
    }

    private function erro(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
