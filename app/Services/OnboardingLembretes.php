<?php

namespace App\Services;

use App\Filament\Pages\PrimeirosPassos;
use App\Mail\OnboardingLembreteMail;
use App\Models\OnboardingInicio;
use App\Models\OnboardingLembrete;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lembretes do onboarding por e-mail, para os administradores das empresas que ainda não terminaram: dia 0 (boas-vindas), dia 2 (passo 1
 * parado), dia 5 (passo 3 parado) e dia 7 (como foi, com sugestão de outra solução). Cada lembrete sai UMA vez por empresa (log com unique),
 * só em dias úteis entre 9h e 18h no fuso configurado. WhatsApp: a Oravel não tem envio utilizável para os administradores das empresas, então só e-mail.
 */
class OnboardingLembretes
{
    public function __construct(private OnboardingService $onboarding) {}

    /** @return array<string, int> quantos lembretes de cada tipo foram enviados */
    public function executar(?Carbon $agora = null, bool $ignorarJanela = false): array
    {
        $cfg = config('onboarding.lembretes');
        $agora = ($agora ?? now())->copy()->setTimezone($cfg['fuso']);
        $enviados = ['boas_vindas' => 0, 'passo_1' => 0, 'passo_3' => 0, 'sete_dias' => 0];

        if (! $ignorarJanela && (! $this->naJanela($agora, $cfg))) {
            return $enviados;
        }

        // Parte das empresas (não das telas abertas): quem nunca entrou no sistema também precisa do lembrete. O início do onboarding é criado aqui se faltar.
        Tenant::withoutGlobalScopes()->where('created_at', '>=', $agora->copy()->subDays(15))->orderBy('created_at')->each(function (Tenant $tenant) use ($agora, &$enviados) {
            $estado = $this->onboarding->estado($tenant);

            if (! $estado) {
                return;
            }

            $inicio = OnboardingInicio::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('solution', $estado['solucao'])->first();

            $dias = (int) $inicio->iniciado_em->copy()->setTimezone($agora->timezone)->startOfDay()->diffInDays($agora->copy()->startOfDay());
            $feitos = collect($estado['passos'])->pluck('concluido', 'chave');
            $passos = array_column($estado['passos'], 'chave');

            foreach ($this->regras($dias, $estado, $feitos, $passos) as $tipo) {
                if ($this->enviar($tenant, $estado, $tipo)) {
                    $enviados[$tipo]++;
                }
            }
        });

        return $enviados;
    }

    /** Quais lembretes cabem para a empresa agora (cada um tem uma janela de dias e uma condição). @return list<string> */
    private function regras(int $dias, array $estado, $feitos, array $passos): array
    {
        $tipos = [];

        if (! $estado['completo']) {
            if ($dias <= 1) {
                $tipos[] = 'boas_vindas';
            }
            if ($dias >= 2 && $dias <= 4 && ! $feitos->get($passos[0])) {
                $tipos[] = 'passo_1';
            }
            if ($dias >= 5 && $dias <= 6 && ! $feitos->get(end($passos))) {
                $tipos[] = 'passo_3';
            }
        } elseif ($dias >= 7 && $dias <= 14) {
            $tipos[] = 'sete_dias';
        }

        return $tipos;
    }

    private function enviar(Tenant $tenant, array $estado, string $tipo): bool
    {
        $emails = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('email')->get()->filter(fn (User $u) => $u->isAdmin())->pluck('email')->unique()->values()->all();

        if ($emails === []) {
            return false;
        }

        // Idempotência: a linha do log é criada ANTES do envio (unique); se o envio falhar ela é removida para tentar de novo na próxima hora.
        $registro = OnboardingLembrete::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'solution' => $estado['solucao'], 'lembrete' => $tipo],
            ['enviado_em' => now()],
        );

        if (! $registro->wasRecentlyCreated) {
            return false;
        }

        try {
            Mail::to($emails)->send($this->mensagem($tenant, $estado, $tipo));

            return true;
        } catch (\Throwable $e) {
            $registro->delete();
            Log::warning('Lembrete de onboarding não enviado', ['tenant' => $tenant->id, 'tipo' => $tipo, 'erro' => $e->getMessage()]);

            return false;
        }
    }

    private function mensagem(Tenant $tenant, array $estado, string $tipo): OnboardingLembreteMail
    {
        $link = PrimeirosPassos::getUrl();
        $atual = $estado['atual'] !== null ? $estado['passos'][$estado['atual']] : null;
        $ajuda = 'https://wa.me/'.config('onboarding.whatsapp').'?text='.rawurlencode("Olá! Sou da empresa {$tenant->name} e preciso de ajuda com os primeiros passos no Oravel.");
        $saudacao = "Olá! Aqui é a Oravel, falando com a {$tenant->name}.";

        return match ($tipo) {
            'boas_vindas' => new OnboardingLembreteMail(
                'Bem-vindo à Oravel: comece por aqui', $saudacao,
                ["Sua conta da solução {$estado['titulo']} está pronta. Em cerca de {$estado['minutos_restantes']} minutos você ".$estado['meta'].'.', 'Preparamos um passo a passo curto que se completa sozinho conforme você usa o sistema.'],
                'Abrir os primeiros passos', $link, $ajuda,
            ),
            'passo_1' => new OnboardingLembreteMail(
                'Falta pouco: '.mb_strtolower($estado['passos'][0]['titulo']), $saudacao,
                ["Ainda falta concluir o primeiro passo: \"{$estado['passos'][0]['titulo']}\". {$estado['passos'][0]['descricao']}", 'Precisa de ajuda?'],
                'Continuar de onde parei', $link, $ajuda,
            ),
            'passo_3' => new OnboardingLembreteMail(
                'Falta pouco para ver o primeiro resultado', $saudacao,
                ['Faltam poucos passos para '.$estado['meta'].($atual ? ": agora, \"{$atual['titulo']}\"." : '.'), 'Veja onde parou e continue de onde estava.'],
                'Continuar de onde parei', $link, $ajuda,
            ),
            default => new OnboardingLembreteMail(
                'Como foi começar com a Oravel?', $saudacao,
                ['Você concluiu os primeiros passos da solução '.$estado['titulo'].'. Como foi a experiência? Responda este e-mail ou fale com a gente: queremos saber o que melhorar.', 'Quando quiser ampliar, conheça as outras soluções da Oravel.'],
                'Ver outras soluções', 'https://oravel.com.br/planos/', $ajuda,
            ),
        };
    }

    private function naJanela(Carbon $agora, array $cfg): bool
    {
        if (($cfg['dias_uteis'] ?? true) && $agora->isWeekend()) {
            return false;
        }

        return $agora->hour >= (int) $cfg['hora_inicio'] && $agora->hour < (int) $cfg['hora_fim'];
    }
}
