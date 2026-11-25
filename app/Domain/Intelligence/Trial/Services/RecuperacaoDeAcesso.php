<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Support\AposResposta;
use App\Domain\Intelligence\Trial\Support\TelefoneBr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Pedido de novo link de acesso. REGRA: saber o WhatsApp NUNCA basta para entrar na conta: o link nunca é mostrado nem devolvido a quem
 * pediu. Só sai por canal que pertence ao dono (e-mail cadastrado na contratação, WhatsApp da Oravel quando existir) ou pela Oravel
 * (Central > Testes do Intelligence > "Gerar link de acesso"), que avisa o dono e envia ao número cadastrado.
 * O pedido é silencioso para quem pediu (a resposta da tela é sempre a mesma), e limitado a 1 por conta a cada 10 minutos.
 */
class RecuperacaoDeAcesso
{
    public function __construct(private EnviadorWhatsApp $whatsapp) {}

    public function solicitar(IntelligenceTrial $trial): void
    {
        if (! Cache::add('intelligence-trial-link:'.$trial->id, 1, 600)) {
            return;
        }

        $enviou = false;

        $email = $trial->user?->email;
        if ($email && ! str_ends_with($email, '.invalid')) {
            $enviou = $this->porEmail($trial, $email);
        }
        if (! $enviou && $this->whatsapp->disponivel()) {
            $enviou = $this->porWhatsApp($trial);
        }

        if ($enviou) {
            Log::info('Intelligence trial: novo link enviado ao dono.', ['trial_id' => $trial->id]);

            return;
        }

        Log::info('Intelligence trial: novo link pedido, sem canal seguro; dono da Oravel avisado.', ['trial_id' => $trial->id]);
        $this->avisarOravel($trial);
    }

    private function porEmail(IntelligenceTrial $trial, string $email): bool
    {
        // O link só é trocado depois de haver para onde mandar o novo; sem e-mail válido o anterior continua valendo.
        $link = route('intelligence.trial.entrar', $trial->novoTokenDeAcesso());
        try {
            Mail::to($email)->send(new TrialMail('Seu link de acesso ao Oravel Intelligence', ['Use o botão abaixo para entrar, sem senha. Não compartilhe este link.'], 'Entrar', $link));

            return true;
        } catch (Throwable $e) {
            Log::warning('Intelligence trial: e-mail do novo link falhou.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);

            return false;
        }
    }

    private function porWhatsApp(IntelligenceTrial $trial): bool
    {
        $link = route('intelligence.trial.entrar', $trial->novoTokenDeAcesso());

        return $this->whatsapp->enviar($trial->whatsapp, 'Seu link de acesso ao Oravel Intelligence (não compartilhe): '.$link);
    }

    /** Sem canal: o dono recebe o pedido (sem o link) e gera/envia pela Central. */
    private function avisarOravel(IntelligenceTrial $trial): void
    {
        $para = config('intelligence_trial.notificar_email') ?: ((array) config('oravel.super_admins', []))[0] ?? null;
        if (! $para) {
            return;
        }
        AposResposta::executar(function () use ($trial, $para) {
            try {
                Mail::to($para)->send(new TrialMail(
                    'Pedido de novo link: '.$trial->empresa,
                    ['Alguém pediu novo link de acesso para '.$trial->empresa.' ('.$trial->nome.', WhatsApp '.TelefoneBr::formatar($trial->whatsapp).').', 'Se for mesmo o cliente, gere o link em Central > Testes do Intelligence > "Gerar link de acesso" e envie para esse WhatsApp.'],
                ));
            } catch (Throwable $e) {
                Log::warning('Intelligence trial: aviso do pedido de link não enviado.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
            }
        });
    }
}
