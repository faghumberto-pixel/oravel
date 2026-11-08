<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/** G1 -- principais MOTIVOS de Inapto / Apto com restricao na frota (agrega o cache de aptidao dos ativos). */
class AptidaoMotivosWidget extends Widget
{
    protected static string $view = 'filament.widgets.aptidao-motivos';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CatalogoAcesso::podeLer() && AptidaoGate::ligada(Tenancy::current()?->id ?? auth()->user()?->tenant_id);
    }

    /** @return array<int, array{texto: string, nivel: string, ativos: int}> */
    public function motivos(): array
    {
        $contagem = [];

        Asset::query()->whereIn('aptidao_status', ['inapto', 'apto_restricao'])->get(['id', 'aptidao_status', 'aptidao_motivos'])
            ->each(function (Asset $a) use (&$contagem) {
                foreach ((array) ($a->aptidao_motivos['motivos'] ?? []) as $m) {
                    if (($m['nivel'] ?? 'apto') === 'apto') {
                        continue;
                    }

                    $chave = $m['codigo'];
                    $contagem[$chave] ??= ['texto' => $m['texto'], 'nivel' => $m['nivel'], 'ativos' => 0];
                    $contagem[$chave]['ativos']++;
                }
            });

        usort($contagem, fn ($x, $y) => [$y['nivel'] === 'inapto', $y['ativos']] <=> [$x['nivel'] === 'inapto', $x['ativos']]);

        return array_slice($contagem, 0, 10);
    }
}
