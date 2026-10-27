<?php

namespace App\Dashboards;

/** Gráficos em SVG puro (sem biblioteca), no mesmo desenho do exemplo aprovado. Cores vêm de variáveis CSS. */
class Svg
{
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
