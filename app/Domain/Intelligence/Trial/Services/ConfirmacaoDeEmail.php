<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrialPendente;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cadastro do teste só vira conta depois de o dono clicar no link enviado ao e-mail digitado. Antes disso nada é criado (nem tenant,
 * nem usuário, nem lead, nem aviso ao dono da Oravel): quem digita e-mail alheio ou lixo não gera nada. A resposta da tela é sempre a
 * mesma, exista conta ou não (a tela nunca revela se um e-mail/WhatsApp já tem teste).
 */
class ConfirmacaoDeEmail
{
    public function __construct(private TrialCadastro $cadastro, private RecuperacaoDeAcesso $recuperacao) {}

    /** @param  array{nome: string, empresa: string, whatsapp: string, email: string}  $d  whatsapp em E.164 */
    public function solicitar(array $d, ?string $ip, ?string $ua): void
    {
        $d['email'] = Str::lower(trim($d['email']));
        IntelligenceTrialPendente::where('expires_at', '<', now())->delete();

        // WhatsApp que já tem teste: nada novo; o link volta ao dono pelo canal dele (ver RecuperacaoDeAcesso).
        $existente = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $d['whatsapp'])->first();
        if ($existente) {
            $this->recuperacao->solicitar($existente);

            return;
        }

        // Uma mensagem por e-mail a cada 2 minutos: o formulário não vira disparador de e-mail para terceiros.
        if (! Cache::add('intelligence-trial-pend:'.sha1($d['email']), 1, 120)) {
            return;
        }

        if (User::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$d['email']])->exists()) {
            $this->enviar($d['email'], 'Você já tem acesso ao Oravel Intelligence', ['Este e-mail já tem cadastro. Entre pelo endereço abaixo, ou peça um novo link de acesso se o teste grátis foi feito com WhatsApp.'], 'Entrar', route('intelligence.trial.novo-link'));

            return;
        }

        $token = Str::random(48);
        DB::transaction(function () use ($d, $token, $ip, $ua) {
            IntelligenceTrialPendente::where('email', $d['email'])->orWhere('whatsapp', $d['whatsapp'])->delete();
            IntelligenceTrialPendente::create($d + [
                'token_hash' => hash('sha256', $token), 'ip' => $ip, 'user_agent' => $ua ? Str::limit($ua, 500, '') : null,
                'expires_at' => now()->addHours(max(1, (int) config('intelligence_trial.confirmacao_horas', 24))),
            ]);
        });

        $this->enviar($d['email'], 'Confirme seu e-mail para começar o teste grátis', [
            'Olá, '.$d['nome'].'! Falta só confirmar o seu e-mail para liberar o teste grátis do Oravel Intelligence.',
            'O link vale por '.(int) config('intelligence_trial.confirmacao_horas', 24).' horas. Se não foi você, ignore esta mensagem: nada será criado.',
        ], 'Confirmar e-mail e entrar', route('intelligence.trial.confirmar', $token));
    }

    /** O link ainda vale? (não consome) */
    public function valido(string $token): bool
    {
        return IntelligenceTrialPendente::porToken($token) !== null;
    }

    /**
     * Consome o link (uso único) e cria a conta. null = link inválido, vencido, já usado ou e-mail que virou de outra conta.
     *
     * @return array{trial: IntelligenceTrial, token: ?string, novo: bool}|null
     */
    public function confirmar(string $token, ?string $ip, ?string $ua): ?array
    {
        $p = IntelligenceTrialPendente::porToken($token);
        if (! $p) {
            return null;
        }
        // Uso único: quem apagar a linha primeiro leva; o segundo clique cai fora.
        if (IntelligenceTrialPendente::where('id', $p->id)->delete() === 0) {
            return null;
        }
        if (User::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$p->email])->exists()) {
            return null;
        }

        return $this->cadastro->cadastrar(['nome' => $p->nome, 'empresa' => $p->empresa, 'whatsapp' => $p->whatsapp, 'email' => $p->email], $p->ip ?? $ip, $p->user_agent ?? $ua);
    }

    private function enviar(string $para, string $assunto, array $paragrafos, string $botao, string $link): void
    {
        try {
            Mail::to($para)->send(new TrialMail($assunto, $paragrafos, $botao, $link));
        } catch (Throwable $e) {
            Log::warning('Intelligence trial: e-mail de confirmação não enviado.', ['erro' => $e->getMessage()]);
        }
    }
}
