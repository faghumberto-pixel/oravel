<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrialLembrete;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Lembretes do teste (dia 20, 27 e 30, configurável). Cada marco sai uma vez por conta (registrado em intelligence_trial_lembretes).
 * Se o comando ficou parado e vários marcos venceram, só o mais recente é enviado; os anteriores ficam registrados como "pulado".
 */
class TrialLembretes
{
    /** Quantos dias depois do marco ele ainda vale (evita lembrete velho depois de uma parada longa). */
    private const TOLERANCIA_DIAS = 2;

    public function __construct(private EnviadorWhatsApp $whatsapp) {}

    /** @return array{enviados: int, sem_canal: int} */
    public function executar(?Carbon $agora = null): array
    {
        $agora ??= now();
        $r = ['enviados' => 0, 'sem_canal' => 0];
        $marcos = collect(config('intelligence_trial.lembretes'))->map(fn ($m) => (int) $m)->sort()->values();

        IntelligenceTrial::withoutGlobalScopes()
            ->whereNotNull('trial_started_at')->whereNull('canceled_at')->whereNull('contracted_at')
            ->each(function (IntelligenceTrial $trial) use ($agora, $marcos, &$r) {
                $dia = (int) $trial->trial_started_at->copy()->startOfDay()->diffInDays($agora->copy()->startOfDay()) + 1;
                $jaEnviados = $trial->lembretes()->pluck('marco')->all();

                $vencidos = $marcos->filter(fn ($m) => $dia >= $m && $dia <= $m + self::TOLERANCIA_DIAS && ! in_array($m, $jaEnviados, true));
                if ($vencidos->isEmpty()) {
                    return;
                }
                $marco = $vencidos->last();
                foreach ($vencidos->slice(0, -1) as $velho) {
                    $this->registrar($trial, $velho, 'pulado', $agora);
                }

                $canais = $this->enviar($trial, $marco);
                foreach ($canais ?: ['sem_canal'] as $canal) {
                    $this->registrar($trial, $marco, $canal, $agora);
                }
                $canais ? $r['enviados']++ : $r['sem_canal']++;
                Log::info('Intelligence trial: lembrete.', ['trial_id' => $trial->id, 'marco' => $marco, 'canais' => $canais ?: ['sem_canal']]);
            });

        return $r;
    }

    private function registrar(IntelligenceTrial $trial, int $marco, string $canal, Carbon $quando): void
    {
        IntelligenceTrialLembrete::firstOrCreate(['trial_id' => $trial->id, 'marco' => $marco, 'canal' => $canal], ['enviado_em' => $quando]);
    }

    /** @return list<string> canais em que a mensagem realmente saiu */
    private function enviar(IntelligenceTrial $trial, int $marco): array
    {
        $restantes = $trial->diasRestantes();
        $texto = $restantes > 0
            ? "Seu teste grátis do Oravel Intelligence termina em {$restantes} dia(s), em {$trial->trial_ends_at->format('d/m/Y')}."
            : 'Hoje é o último dia do seu teste grátis do Oravel Intelligence.';
        $assunto = $restantes > 0 ? "Seu teste do Intelligence termina em {$restantes} dia(s)" : 'Último dia do seu teste do Intelligence';
        $link = route('intelligence.trial.contratar');
        $canais = [];

        $email = $trial->user?->email;
        if ($email && ! str_ends_with($email, '.invalid')) {
            try {
                Mail::to($email)->send(new TrialMail($assunto, [$texto, 'Depois do teste, o histórico e os painéis continuam visíveis, mas novos envios ficam bloqueados. Contrate o plano básico para continuar enviando.'], 'Contratar plano básico', $link));
                $canais[] = 'email';
            } catch (Throwable $e) {
                Log::warning('Intelligence trial: lembrete por e-mail falhou.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
            }
        }

        if ($this->whatsapp->disponivel() && $this->whatsapp->enviar($trial->whatsapp, $texto.' Contrate em: '.$link)) {
            $canais[] = 'whatsapp';
        }

        return $canais;
    }
}
