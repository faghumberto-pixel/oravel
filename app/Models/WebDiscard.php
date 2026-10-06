<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Contador diário do que o coletor do site descarta (ver SiteTrackingController).
 * Global de propósito (sem tenant): é a operação do SaaS medindo o próprio site.
 */
class WebDiscard extends Model
{
    public const REASON_ROBOT = 'robo_navegador';

    public const REASON_DATACENTER = 'servidor_nuvem';

    public const REASON_ORIGIN = 'origem_invalida';

    protected $fillable = ['day', 'reason', 'hits', 'sessions'];

    protected $casts = ['day' => 'date'];

    /** @return array<string, string> */
    public static function reasonLabels(): array
    {
        return [
            self::REASON_ROBOT => 'Robô (pelo navegador)',
            self::REASON_DATACENTER => 'IP de servidor/nuvem (robô ou pré-visualização)',
            self::REASON_ORIGIN => 'Origem não autorizada',
        ];
    }

    /** Conta uma página aberta descartada; a sessão só conta 1x por dia e motivo. */
    public static function record(string $reason, ?string $session = null): void
    {
        $day = now()->toDateString();
        $newSession = $session && Cache::add("web-discard:{$day}:{$reason}:{$session}", 1, now()->addDays(2));

        DB::table('web_discards')->upsert(
            [['day' => $day, 'reason' => $reason, 'hits' => 1, 'sessions' => $newSession ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]],
            ['day', 'reason'],
            ['hits' => DB::raw('web_discards.hits + 1'), 'sessions' => DB::raw('web_discards.sessions + '.($newSession ? 1 : 0)), 'updated_at' => now()]
        );
    }
}
