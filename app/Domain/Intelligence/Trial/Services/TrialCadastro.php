<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\NovoCadastroTrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Support\AposResposta;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Services\TenantTimeline;
use App\Support\Pacotes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cadastro (nome, empresa, WhatsApp e e-mail confirmado): cria Tenant + usuário pelo caminho padrão (TenantProvisioner), a conta de
 * teste e o lead no CRM da Oravel. WhatsApp que já tem conta NÃO cria teste novo (não reinicia os 30 dias): devolve o acesso da existente.
 */
class TrialCadastro
{
    public const TAG_PLANO_TESTE = 'intelligence_trial';

    public function __construct(private TrialCiclo $ciclo) {}

    /**
     * @param  array{nome: string, empresa: string, whatsapp: string, email?: string}  $dados  whatsapp já em E.164; e-mail já confirmado
     * @return array{trial: IntelligenceTrial, token: ?string, novo: bool} token só existe quando a conta foi criada agora
     */
    public function cadastrar(array $dados, ?string $ip = null, ?string $userAgent = null): array
    {
        // Cadastro novo começa com a sessão limpa. Uma sessão antiga (de um usuário que já foi removido, por exemplo) fazia o evento da
        // linha do tempo ser gravado com um autor inexistente: a chave estrangeira falha e, no Postgres, a transação aborta (erro 500).
        if (app()->bound('request') && request()->hasSession()) {
            Auth::guard('web')->logout();
        }

        $existente = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $dados['whatsapp'])->first();
        if ($existente) {
            // Saber o WhatsApp não dá acesso: não troca o link do dono nem devolve token (ver RecuperacaoDeAcesso).
            Log::info('Intelligence trial: cadastro repetido, nada criado.', ['trial_id' => $existente->id]);

            return ['trial' => $existente, 'token' => null, 'novo' => false];
        }

        try {
            $trial = DB::transaction(fn () => $this->criar($dados, $ip, $userAgent));
        } catch (UniqueConstraintViolationException) {
            // Duas requisições simultâneas com o mesmo número: a segunda cai aqui e usa a conta da primeira.
            $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $dados['whatsapp'])->firstOrFail();

            return ['trial' => $trial, 'token' => null, 'novo' => false];
        }

        $token = $trial->novoTokenDeAcesso();
        $this->registrarLead($trial);
        $this->avisarDono($trial);
        Log::info('Intelligence trial: novo cadastro.', ['trial_id' => $trial->id, 'tenant_id' => $trial->tenant_id]);

        return ['trial' => $trial, 'token' => $token, 'novo' => true];
    }

    private function criar(array $d, ?string $ip, ?string $ua): IntelligenceTrial
    {
        $plano = $this->planoDeTeste();

        $tenant = Tenant::create([
            'name' => $d['empresa'],
            'slug' => $this->slugUnico($d['empresa']),
            'plan_id' => $plano->id,
            'status' => 'trial',
            'mrr_value' => 0,
            'telefone' => $d['whatsapp'],
            'terms_accepted_at' => now(),
        ]);

        // Sem senha: o acesso é pelo link pessoal. A senha existe só para a coluna não ficar vazia e nunca é mostrada.
        // O e-mail é o confirmado pelo cliente (ConfirmacaoDeEmail); sem ele (uso interno) vale um provisório .invalid.
        $user = TenantProvisioner::provision($tenant, [
            'name' => $d['nome'],
            'email' => ! empty($d['email']) ? Str::lower($d['email']) : 'teste-'.Str::lower(Str::random(10)).'@intelligence.oravel.invalid',
            'password' => Str::random(40),
        ]);
        // two_factor_exempt: o acesso é pelo link pessoal; não existe senha nem app autenticador para exigir 2FA (senão o cliente
        // cai em "Minha conta" e nunca chega ao upload).
        $user->forceFill(['is_approved' => true, 'two_factor_exempt' => true, 'email_verified_at' => ! empty($d['email']) ? now() : null])->save();

        $trial = IntelligenceTrial::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'nome' => $d['nome'],
            'empresa' => $d['empresa'],
            'whatsapp' => $d['whatsapp'],
            'status' => 'trial',
            'terms_accepted_at' => now(),
            'signup_ip' => $ip,
            'signup_user_agent' => $ua ? Str::limit($ua, 500, '') : null,
        ]);

        // Modo "contar desde o cadastro" (config): o relógio já começa agora.
        if (config('intelligence_trial.inicio') === 'cadastro') {
            $this->ciclo->iniciarContagem($trial);
        }

        TenantTimeline::record($tenant, TenantEvent::ANOTACAO, 'Teste grátis do Intelligence iniciado', 'Cadastro rápido pelo site.', dedupeKey: 'intelligence-trial-cadastro');

        return $trial;
    }

    /**
     * Contrato (Plan) interno do teste: sempre ATIVO e com os módulos do pacote. O plano de vitrine não serve: nasce inativo até a
     * Central abri-lo para venda, e Plan::hasFeature() nega tudo de plano inativo (o cliente cairia em 403). Preço 0 e tag própria
     * mantêm este plano fora da listagem pública e do checkout.
     */
    private function planoDeTeste(): Plan
    {
        $pacote = (string) config('intelligence_trial.pacote');
        $features = array_fill_keys(Pacotes::features($pacote), true);

        $plano = Plan::withoutGlobalScopes()->where('campaign_tag', self::TAG_PLANO_TESTE)->where('pacote', $pacote)->first();
        if (! $plano) {
            return Plan::create([
                'name' => 'Oravel Intelligence - Teste grátis', 'pacote' => $pacote, 'campaign_tag' => self::TAG_PLANO_TESTE,
                'level' => 1, 'price' => 0, 'base_price' => 0, 'billing_cycle' => 'monthly', 'features' => $features, 'is_active' => true,
            ]);
        }
        if ($plano->features != $features || ! $plano->is_active) {
            $plano->update(['features' => $features, 'is_active' => true]);
        }

        return $plano;
    }

    private function slugUnico(string $nome): string
    {
        $base = Str::slug($nome) ?: 'empresa';
        $slug = $base;
        while (Tenant::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    /** Lead no funil de vendas da Oravel (Central). Falha aqui nunca derruba o cadastro. */
    private function registrarLead(IntelligenceTrial $trial): void
    {
        try {
            $lead = SalesLead::create([
                'company_name' => $trial->empresa,
                'phone' => $trial->whatsapp,
                'source' => SalesLead::SOURCE_SITE,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'decision_makers' => [['name' => $trial->nome, 'role' => null]],
                'inbound_details' => ['origem' => 'Intelligence – teste grátis', 'whatsapp' => $trial->whatsapp, 'trial_id' => $trial->id],
            ]);

            $autor = User::withoutGlobalScopes()->whereIn(DB::raw('lower(email)'), array_map('strtolower', (array) config('oravel.super_admins', [])))->orderBy('created_at')->first();
            if ($autor) {
                SalesLeadInteraction::create([
                    'sales_lead_id' => $lead->id,
                    'user_id' => $autor->id,
                    'channel' => 'Site — Intelligence – teste grátis',
                    'contact_date' => now(),
                    'summary' => 'Cadastro rápido do teste grátis do Intelligence. Responsável: '.$trial->nome.' | WhatsApp: '.$trial->whatsapp,
                    'stage_at_time' => SalesLead::STAGE_PROSPECCAO,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Intelligence trial: lead não registrado no CRM.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
        }
    }

    private function avisarDono(IntelligenceTrial $trial): void
    {
        $para = config('intelligence_trial.notificar_email') ?: ((array) config('oravel.super_admins', []))[0] ?? null;
        if (! $para) {
            return;
        }
        AposResposta::executar(function () use ($trial, $para) {
            try {
                Mail::to($para)->send(new NovoCadastroTrialMail($trial));
            } catch (Throwable $e) {
                Log::warning('Intelligence trial: aviso ao dono não enviado.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
            }
        });
    }
}
