<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrialPendente;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    public function __construct(private TrialCadastro $cadastro) {}

    /** @param  array{nome: string, empresa: string, whatsapp: string, email: string, senha: string}  $d  whatsapp em E.164; a senha só é guardada como hash */
    public function solicitar(array $d, ?string $ip, ?string $ua): void
    {
        $d['email'] = Str::lower(trim($d['email']));
        $senha = (string) ($d['senha'] ?? '');
        unset($d['senha']); // nunca segue adiante em texto
        IntelligenceTrialPendente::where('expires_at', '<', now())->delete();

        // WhatsApp que já tem teste: nada novo. Quem digitou o número não ganha acesso nem altera nada: o DONO recebe um aviso no
        // e-mail dele, com o caminho para entrar ou redefinir a senha (nunca um link que entre sozinho).
        $existente = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $d['whatsapp'])->first();
        if ($existente) {
            $this->avisarDono($existente);

            return;
        }

        // Uma mensagem por e-mail a cada 2 minutos: o formulário não vira disparador de e-mail para terceiros.
        if (! Cache::add('intelligence-trial-pend:'.sha1($d['email']), 1, 120)) {
            return;
        }

        if (User::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$d['email']])->exists()) {
            $this->enviar($d['email'], 'Você já tem acesso ao Oravel Intelligence', ['Este e-mail já tem cadastro. Entre com o seu e-mail e a sua senha. Se esqueceu a senha, use "Esqueci minha senha" na tela de entrada.'], 'Entrar', $this->urlDeEntrada());

            return;
        }

        $token = Str::random(48);
        DB::transaction(function () use ($d, $senha, $token, $ip, $ua) {
            IntelligenceTrialPendente::where('email', $d['email'])->orWhere('whatsapp', $d['whatsapp'])->delete();
            IntelligenceTrialPendente::create($d + [
                'senha_hash' => Hash::make($senha), 'token_hash' => hash('sha256', $token), 'ip' => $ip, 'user_agent' => $ua ? Str::limit($ua, 500, '') : null,
                'expires_at' => now()->addHours(max(1, (int) config('intelligence_trial.confirmacao_horas', 24))),
            ]);
        });

        $this->enviar($d['email'], 'Confirme seu e-mail para começar o teste grátis', [
            'Olá, '.$d['nome'].'! Falta só confirmar o seu e-mail para liberar o teste grátis do Oravel Intelligence.',
            'O link vale por '.(int) config('intelligence_trial.confirmacao_horas', 24).' horas. Se não foi você, ignore esta mensagem: nada será criado.',
        ], 'Confirmar e-mail', route('intelligence.trial.confirmar', $token));
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

        return $this->cadastro->cadastrar(['nome' => $p->nome, 'empresa' => $p->empresa, 'whatsapp' => $p->whatsapp, 'email' => $p->email, 'senha_hash' => $p->senha_hash, 'objetivo' => $p->objetivo], $p->ip ?? $ip, $p->user_agent ?? $ua);
    }

    /** Tela de entrada com e-mail e senha (é o único caminho de acesso). */
    private function urlDeEntrada(): string
    {
        return Filament::getPanel('admin')->getLoginUrl();
    }

    /** Aviso ao dono de uma conta: alguém tentou criar um cadastro com o WhatsApp dele. Sem link que entre; só o caminho normal. */
    private function avisarDono(IntelligenceTrial $trial): void
    {
        $email = $trial->user?->email;
        if (! $email || str_ends_with($email, '.invalid') || ! Cache::add('intelligence-trial-aviso:'.$trial->id, 1, 600)) {
            return;
        }
        $this->enviar($email, 'Tentaram criar um cadastro com o seu WhatsApp', [
            'Alguém tentou criar um teste do Oravel Intelligence com o WhatsApp da sua conta. Nada foi alterado e ninguém ganhou acesso.',
            'Se foi você, entre com o seu e-mail e a sua senha. Se esqueceu a senha, use "Esqueci minha senha" na tela de entrada.',
        ], 'Entrar', $this->urlDeEntrada());
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
