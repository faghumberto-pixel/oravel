<?php

namespace App\Dashboards;

/** Gráficos em SVG puro (sem biblioteca), no mesmo desenho do exemplo aprovado. Cores vêm de variáveis CSS. */
class Svg
{
    /** Minigrafico de area (sparkline) para o KPI. Cor segue o tom (ok/warn/crit/n) ou o acento. */
    public static function spark(array $values, string $tone = 'a'): string
    {
        $values = array_values(array_map('floatval', $values));
        if (count($values) < 2) {
            return '';
        }
        $cor = ['ok' => 'var(--odb-ok)', 'warn' => 'var(--odb-warn)', 'crit' => 'var(--odb-crit)', 'n' => 'var(--odb-muted)'][$tone] ?? 'var(--odb-accent)';
        $W = 160;
        $H = 44;
        $max = max(1.0, max($values));
        $passo = $W / (count($values) - 1);
        $pts = [];
        foreach ($values as $i => $v) {
            $pts[] = [round($i * $passo, 1), round($H - 4 - ($H - 10) * $v / $max, 1)];
        }
        $linha = implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $pts));
        $area = '0,'.$H.' '.$linha.' '.$W.','.$H;
        $ultimo = end($pts);

        return '<svg viewBox="0 0 '.$W.' '.$H.'" preserveAspectRatio="none" role="img" aria-label="Evolução nos últimos meses" style="height:44px">'
            .'<polygon points="'.$area.'" fill="'.$cor.'" opacity=".16"/>'
            .'<polyline points="'.$linha.'" fill="none" stroke="'.$cor.'" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>'
            .'<circle cx="'.$ultimo[0].'" cy="'.$ultimo[1].'" r="3" fill="'.$cor.'"/></svg>';
    }

    /** Anel com fatias proporcionais e texto no centro. `$fatias`: [rotulo, valor, cor css]. */
    public static function donut(array $fatias, string $centro, string $sub = ''): string
    {
        $total = array_sum(array_map(fn ($f) => max(0, (float) $f[1]), $fatias));
        $r = 46;
        $c = 2 * M_PI * $r;
        $s = '<circle cx="60" cy="60" r="'.$r.'" fill="none" stroke="var(--odb-track)" stroke-width="14"/>';

        if ($total > 0) {
            $acumulado = 0.0;
            foreach ($fatias as $f) {
                $v = max(0, (float) $f[1]);
                if ($v <= 0) {
                    continue;
                }
                $comp = $v / $total * $c;
                $s .= '<circle cx="60" cy="60" r="'.$r.'" fill="none" stroke="'.e($f[2]).'" stroke-width="14" stroke-dasharray="'.round(max(0, $comp - 1.5), 2).' '.round($c, 2).'" stroke-dashoffset="'.round(-$acumulado, 2).'" transform="rotate(-90 60 60)"><title>'.e($f[0]).': '.e((string) $f[1]).'</title></circle>';
                $acumulado += $comp;
            }
        }

        $tamanho = mb_strlen($centro) > 6 ? 13 : 17; // numero longo (R$ 211 mil) precisa caber dentro do anel
        $s .= '<text x="60" y="'.($sub !== '' ? 58 : 65).'" text-anchor="middle" style="font:700 '.$tamanho.'px sans-serif;fill:var(--odb-fg)">'.e($centro).'</text>';
        if ($sub !== '') {
            $s .= '<text x="60" y="73" text-anchor="middle" style="font:11px sans-serif;fill:var(--odb-muted)">'.e($sub).'</text>';
        }

        return '<svg viewBox="0 0 120 120" role="img" aria-label="Gráfico em anel" style="max-width:150px;margin:0 auto">'.$s.'</svg>';
    }

    public static function cols(array $labels, array $a, array $b): string
    {
        $W = 520;
        $H = 190;
        $pl = 36;
        $pb = 22;
        $pt = 8;
        $max = max(50, (int) (ceil(max(array_merge($a, $b, [1])) / 50) * 50));
        $n = max(count($labels), 1);
        $gw = ($W - $pl) / $n;
        $bw = $gw * .30;
        $s = static::grid($W, $H, $pl, $pb, $pt, $max);

        foreach ($labels as $i => $label) {
            $x = $pl + $gw * $i + $gw / 2;
            $ha = ($H - $pt - $pb) * ($a[$i] ?? 0) / $max;
            $hb = ($H - $pt - $pb) * ($b[$i] ?? 0) / $max;
            $s .= '<rect x="'.round($x - $bw - 1, 1).'" y="'.round($H - $pb - $ha, 1).'" width="'.round($bw, 1).'" height="'.round($ha, 1).'" rx="2" fill="var(--odb-accent)"/>'
                .'<rect x="'.round($x + 1, 1).'" y="'.round($H - $pb - $hb, 1).'" width="'.round($bw, 1).'" height="'.round($hb, 1).'" rx="2" fill="var(--odb-fg)" opacity=".55"/>'
                .'<text x="'.round($x, 1).'" y="'.($H - 6).'" text-anchor="middle">'.e($label).'</text>';
        }

        return '<svg viewBox="0 0 '.$W.' '.$H.'" role="img" aria-label="Gráfico de colunas">'.$s.'</svg>';
    }

    public static function line(array $labels, array $values, int|float|null $limit = null): string
    {
        $values = array_values($values);
        if (count($values) < 2) {
            return '';
        }

        $W = 520;
        $H = 170;
        $pl = 36;
        $pb = 22;
        $pt = 10;
        $max = max(1, (int) ceil(max(array_merge($values, [$limit ?? 0])) * 1.15));
        $n = count($values);
        $s = static::grid($W, $H, $pl, $pb, $pt, $max);
        $pts = [];

        foreach ($values as $i => $v) {
            $px = $pl + ($W - $pl - 14) * $i / ($n - 1) + 4;
            $py = $pt + ($H - $pt - $pb) * (1 - $v / $max);
            $pts[] = [round($px, 1), round($py, 1)];
            $s .= '<text x="'.round($px, 1).'" y="'.($H - 6).'" text-anchor="middle">'.e($labels[$i] ?? '').'</text>';
        }

        if ($limit) {
            $ly = round($pt + ($H - $pt - $pb) * (1 - $limit / $max), 1);
            $s .= '<line x1="'.$pl.'" x2="'.$W.'" y1="'.$ly.'" y2="'.$ly.'" stroke="var(--odb-crit)" stroke-dasharray="4 3"/>'
                .'<text x="'.$W.'" y="'.($ly - 4).'" text-anchor="end" style="fill:var(--odb-crit)">meta '.$limit.'</text>';
        }

        $d = implode(' ', array_map(fn ($p, $i) => ($i ? 'L' : 'M').$p[0].' '.$p[1], $pts, array_keys($pts)));
        $last = $pts[$n - 1];
        $s .= '<path d="'.$d.' L'.$last[0].' '.($H - $pb).' L'.$pts[0][0].' '.($H - $pb).'Z" fill="var(--odb-accent)" opacity=".14"/>'
            .'<path d="'.$d.'" fill="none" stroke="var(--odb-accent)" stroke-width="2.4"/>'
            .'<circle cx="'.$last[0].'" cy="'.$last[1].'" r="4.5" fill="var(--odb-accent)"/>';

        return '<svg viewBox="0 0 '.$W.' '.$H.'" role="img" aria-label="Gráfico de linha">'.$s.'</svg>';
    }

    private static function grid(int $W, int $H, int $pl, int $pb, int $pt, int $max): string
    {
        $s = '';
        for ($g = 0; $g <= 4; $g++) {
            $y = round($pt + ($H - $pt - $pb) * (1 - $g / 4), 1);
            $s .= '<line x1="'.$pl.'" x2="'.$W.'" y1="'.$y.'" y2="'.$y.'" stroke="currentColor" opacity=".13"/>'
                .'<text x="'.($pl - 6).'" y="'.($y + 4).'" text-anchor="end">'.round($max * $g / 4).'</text>';
        }

        return $s;
    }
}
