<?php

namespace App\Services\Frota;

use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Elo entre a Gestão de Frota e o Almoxarifado (Peças e Insumos). Pneus, baterias e óleo guardam a peça e o
 * almoxarifado de onde saem; sem esses dois campos nada muda no estoque (comportamento antigo).
 */
class EstoqueFrotaService
{
    /** Entrada de unidades no almoxarifado (cadastro de pneu/bateria, devolução ao estoque). */
    public function entrada(?Model $item, int $quantidade, string $tipo, string $documento): ?StockMovement
    {
        if (! $this->vinculado($item)) {
            return null;
        }
        $peca = Part::find($item->peca_id);
        $custo = (float) ($item->custo ?? $peca?->cost_price ?? 0);

        return $this->executar(fn () => app(StockMovementService::class)->recordEntry(
            (int) $item->almoxarifado_id, (int) $item->peca_id, $quantidade, $custo, $tipo, $documento, 'Gestão de Frota'
        ));
    }

    /** Saída do almoxarifado (montagem, instalação, litros de óleo aplicados). */
    public function saida(?Model $item, int|float $quantidade, string $documento, string $campo = 'estoque'): ?StockMovement
    {
        if (! $this->vinculado($item)) {
            return null;
        }

        return $this->executar(fn () => app(StockMovementService::class)->recordExit(
            (int) $item->almoxarifado_id, (int) $item->peca_id, $quantidade, 'exit_fleet', $documento, 'Gestão de Frota'
        ), $campo);
    }

    private function vinculado(?Model $item): bool
    {
        return $item !== null && filled($item->peca_id) && filled($item->almoxarifado_id);
    }

    /** @throws ValidationException */
    private function executar(\Closure $acao, string $campo = 'estoque'): StockMovement
    {
        try {
            return $acao();
        } catch (\InvalidArgumentException|\Exception $e) {
            throw ValidationException::withMessages([$campo => 'Almoxarifado: '.$e->getMessage()]);
        }
    }

    /** @return array<int, string> peças do cliente (para listas de escolha). */
    public static function opcoesPecas(): array
    {
        return Part::query()->active()->orderBy('name')->get()->mapWithKeys(fn (Part $p) => [$p->id => $p->name.' ('.$p->sku.')'])->all();
    }

    /** @return array<int, string> almoxarifados ativos do cliente. */
    public static function opcoesAlmoxarifados(): array
    {
        return Warehouse::query()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Almoxarifado volante do usuário logado (técnico), se houver: sugerido por padrão no óleo. */
    public static function almoxarifadoDoUsuario(): ?int
    {
        return auth()->id() ? Warehouse::mobileForUser(auth()->id())->value('id') : null;
    }
}
