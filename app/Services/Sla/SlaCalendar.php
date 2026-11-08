<?php

namespace App\Services\Sla;

use App\Models\SlaHoliday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * F5: calendario do SLA. Em 24x7 o prazo e' simplesmente inicio + minutos. No horario comercial o relogio so' corre dentro das
 * janelas de cada dia da semana (no fuso do tenant) e para em feriados. As duas contas que o resto do sistema precisa:
 * somar() (inicio + N minutos de calendario => prazo) e entre() (minutos de calendario entre dois instantes).
 */
class SlaCalendar
{
    public const MODO_24X7 = '24x7';

    public const MODO_COMERCIAL = 'comercial';

    /** @var array<string, self> */
    private static array $cache = [];

    /**
     * @param  array<int, array<int, array{0: string, 1: string}>>  $janelas  dia da semana ISO (1=segunda..7=domingo) => lista de [inicio, fim] "HH:MM"
     * @param  array<string, true>  $feriados  datas "Y-m-d"
     */
    public function __construct(
        public readonly string $modo = self::MODO_24X7,
        public readonly string $timezone = 'America/Sao_Paulo',
        public readonly array $janelas = [],
        public readonly array $feriados = [],
    ) {}

    public static function vinte4x7(): self
    {
        return self::$cache['24x7'] ??= new self;
    }

    /** Segunda a sexta, 08:00 as 18:00. */
    public static function horarioPadrao(): array
    {
        return array_fill_keys([1, 2, 3, 4, 5], [['08:00', '18:00']]);
    }

    /**
     * Normaliza o que vem do banco/formulario: {"1":["08:00","18:00"]} ou {"1":[["08:00","12:00"],["13:00","18:00"]]}.
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    public static function normalizarJanelas(?array $bruto): array
    {
        if (! $bruto) {
            return self::horarioPadrao();
        }

        $saida = [];
        foreach ($bruto as $dia => $janelas) {
            if (empty($janelas)) {
                continue;
            }
            $janelas = is_string($janelas[0] ?? null) ? [$janelas] : $janelas;
            foreach ($janelas as $j) {
                if (isset($j[0], $j[1]) && $j[1] > $j[0]) {
                    $saida[(int) $dia][] = [substr((string) $j[0], 0, 5), substr((string) $j[1], 0, 5)];
                }
            }
        }

        return $saida;
    }

    public static function doTenant(string $tenantId): self
    {
        return self::$cache[$tenantId] ??= (function () use ($tenantId) {
            $s = SlaService::settings($tenantId);

            if ($s->calendar_mode !== self::MODO_COMERCIAL) {
                return self::vinte4x7();
            }

            $feriados = SlaHoliday::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('date')->mapWithKeys(fn ($d) => [$d->format('Y-m-d') => true])->all();

            return new self(self::MODO_COMERCIAL, $s->timezone ?: 'America/Sao_Paulo', self::normalizarJanelas($s->business_hours), $feriados);
        })();
    }

    public static function esquecer(): void
    {
        self::$cache = [];
    }

    public function ehComercial(): bool
    {
        return $this->modo === self::MODO_COMERCIAL && $this->janelas !== [];
    }

    /** Instante em que terminam `$minutos` minutos de calendario contados a partir de `$inicio`. */
    public function somar(CarbonInterface $inicio, float $minutos): CarbonInterface
    {
        if (! $this->ehComercial()) {
            return $inicio->copy()->addSeconds((int) round($minutos * 60));
        }

        $restante = (int) round($minutos * 60);
        $cursor = CarbonImmutable::instance($inicio)->setTimezone($this->timezone);

        for ($i = 0; $i < 800; $i++) {
            foreach ($this->janelasDe($cursor) as [$ini, $fim]) {
                if ($cursor >= $fim) {
                    continue;
                }

                $de = $cursor > $ini ? $cursor : $ini;
                $disponivel = $de->diffInSeconds($fim);

                if ($restante <= $disponivel) {
                    return $this->naHoraDoApp($de->addSeconds($restante));
                }

                $restante -= (int) $disponivel;
                $cursor = $fim;
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        return $inicio->copy()->addSeconds((int) round($minutos * 60)); // calendario sem nenhum dia aberto no horizonte
    }

    /** Minutos de calendario entre dois instantes (0 se `$b` nao e' depois de `$a`). */
    public function entre(CarbonInterface $a, CarbonInterface $b): float
    {
        if ($b->lessThanOrEqualTo($a)) {
            return 0.0;
        }

        if (! $this->ehComercial()) {
            return $a->diffInSeconds($b) / 60;
        }

        $fim = CarbonImmutable::instance($b)->setTimezone($this->timezone);
        $cursor = CarbonImmutable::instance($a)->setTimezone($this->timezone);
        $segundos = 0.0;

        for ($i = 0; $i < 800 && $cursor < $fim; $i++) {
            foreach ($this->janelasDe($cursor) as [$ini, $janelaFim]) {
                $de = $cursor > $ini ? $cursor : $ini;
                $ate = $fim < $janelaFim ? $fim : $janelaFim;

                if ($ate > $de) {
                    $segundos += $de->diffInSeconds($ate);
                }
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        return $segundos / 60;
    }

    /** @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}> janelas do dia de `$dia` como instantes no fuso do tenant */
    private function janelasDe(CarbonImmutable $dia): array
    {
        if (isset($this->feriados[$dia->format('Y-m-d')])) {
            return [];
        }

        return array_map(fn ($j) => [
            $dia->setTimeFromTimeString($j[0])->setSecond(0),
            $dia->setTimeFromTimeString($j[1])->setSecond(0),
        ], $this->janelas[$dia->dayOfWeekIso] ?? []);
    }

    private function naHoraDoApp(CarbonImmutable $c): CarbonInterface
    {
        return Carbon::instance($c)->setTimezone(config('app.timezone'));
    }
}
