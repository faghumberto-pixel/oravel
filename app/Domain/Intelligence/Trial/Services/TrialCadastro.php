<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\NovoCadastroTrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Services\TenantTimeline;
use App\Support\Pacotes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cadastro ultrarrápido (nome, empresa, WhatsApp): cria Tenant + usuário pelo caminho padrão (TenantProvisioner), a conta de
 * teste e o lead no CRM da Oravel. WhatsApp que já tem conta NÃO cria teste novo (não reinicia os 30 dias): devolve o acesso da existente.
 */
class TrialCadastro
{
    public function __construct(private TrialCiclo $ciclo) {}

    /**
     * @param  array{nome: string, empresa: string, whatsapp: string}  $dados  whatsapp já em E.164
     * @return array{trial: IntelligenceTrial, token: string, novo: bool}
     */
    public function cadastrar(array $dados, ?string $ip = null, ?string $userAgent = null): array
    {
        $existente = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $dados['whatsapp'])->first();
        if ($existente) {
            Log::info('Intelligence trial: cadastro repetido, acesso da conta existente.', ['trial_id' => $existente->id]);

            return ['trial' => $existente, 'token' => $existente->novoTokenDeAcesso(), 'novo' => false];
        }

        try {
            $trial = DB::transaction(fn () => $this->criar($dados, $ip, $userAgent));
        } catch (UniqueConstraintViolationException) {
            // Duas requisições simultâneas com o mesmo número: a segunda cai aqui e usa a conta da primeira.
            $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', $dados['whatsapp'])->firstOrFail();

            return ['trial' => $trial, 'token' => $trial->novoTokenDeAcesso(), 'novo' => false];
        }

        $token = $trial->novoTokenDeAcesso();
        $this->registrarLead($trial);
        $this->avisarDono($trial);
        Log::info('Intelligence trial: novo cadastro.', ['trial_id' => $trial->id, 'tenant_id' => $trial->tenant_id]);

        return ['trial' => $trial, 'token' => $token, 'novo' => true];
    }

    private function criar(array $d, ?string $ip, ?string $ua): IntelligenceTrial
    {
        $plano = Pacotes::planoDeVitrine((string) config('intelligence_trial.pacote'));

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
        $user = TenantProvisioner::provision($tenant, [
            'name' => $d['nome'],
            'email' => 'teste-'.Str::lower(Str::random(10)).'@intelligence.oravel.invalid',
            'password' => Str::random(40),
        ]);
        $user->forceFill(['is_approved' => true])->save();

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
        try {
            Mail::to($para)->send(new NovoCadastroTrialMail($trial));
        } catch (Throwable $e) {
            Log::warning('Intelligence trial: aviso ao dono não enviado.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
        }
    }
}
