<?php

namespace App\Http\Controllers;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Filament\Resources\CrmLeadResource;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SiteVisitMatcher;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Validator;

/**
 * Canal de entrada de leads de formulários externos (o site de um tenant), servidor a servidor.
 * O lead vira um CrmLead do tenant dono do canal e a equipe é avisada no sino do painel, sem
 * depender de SMTP -- o motivo de existir: o e-mail (Titan) saiu do ar e os leads ficavam sem registro.
 *
 * Multi-tenant por construção: o token do canal (header X-Channel-Token) identifica o tenant; nada
 * do corpo da requisição escolhe tenant. Hoje há um canal (o site da própria Oravel, tenant
 * comercial 'oravel'); os demais tenants ganham o seu como um novo item em services.inbound.channels
 * (ou, depois, uma tabela por tenant) sem mudar este contrato.
 *
 * Quem chama (site) já validou Turnstile/honeypot; aqui a barreira é o token + throttle na rota.
 * Diferente do LandingPageLeadController, NÃO cai em Tenant::first() quando o tenant do canal não
 * existe: isso jogaria leads no CRM de outro tenant. Falha fechado (503).
 */
class InboundLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $channel = $this->channelFor((string) $request->header('X-Channel-Token'));
        if ($channel === null) {
            // Não distingue "sem token", "token errado" e "nenhum canal ligado": 401 para todos.
            return response()->json(['success' => false, 'message' => 'Não autorizado.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'lead_id' => ['nullable', 'string', 'max:40'],
            'origem' => ['required', 'string', 'regex:/^[a-z0-9-]{1,40}$/'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ()+\-.]{8,30}$/'],
            'segmento' => ['required', 'string', 'max:80'],
            'porte' => ['required', 'string', 'max:30'],
            'porte_label' => ['nullable', 'string', 'max:40'],
            'origem_label' => ['nullable', 'string', 'max:80'],
            // Texto livre opcional (ex.: /contato.php do site, que não tem segmento/porte pra
            // escolher -- ver InboundLeadTest::test_mensagem_livre_opcional_entra_na_interacao).
            'mensagem' => ['nullable', 'string', 'max:2000'],
            // Origem do anúncio (o site repassa o que o visitante trouxe do Google Ads). Tudo opcional.
            'gclid' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'gbraid' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'wbraid' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'utm_source' => ['nullable', 'string', 'max:200'],
            'utm_medium' => ['nullable', 'string', 'max:200'],
            'utm_campaign' => ['nullable', 'string', 'max:200'],
            'utm_term' => ['nullable', 'string', 'max:200'],
            'utm_content' => ['nullable', 'string', 'max:200'],
            'landing_url' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $tenant = Tenant::where('slug', $channel['tenant_slug'])->first();
        if (! $tenant) {
            Log::error('InboundLead: tenant do canal não encontrado', ['canal' => $channel['name'], 'slug' => $channel['tenant_slug']]);

            return response()->json(['success' => false, 'message' => 'Indisponível.'], 503);
        }

        // Idempotência por canal: o site pode repetir o envio (timeout de rede) com o mesmo lead_id.
        if (! empty($data['lead_id']) && ! Cache::add('inbound-lead:'.$channel['name'].':'.$data['lead_id'], true, now()->addDay())) {
            return response()->json(['success' => true, 'duplicate' => true], 200);
        }

        $rotuloPorte = $data['porte_label'] ?? 'Porte';
        $origemLabel = $data['origem_label'] ?? $data['origem'];

        if (($channel['destination'] ?? 'crm') === 'central') {
            return $this->storeInCentral($data, $rotuloPorte, $origemLabel);
        }

        $recipients = $this->recipients($tenant);

        $lead = DB::transaction(function () use ($tenant, $data, $rotuloPorte, $origemLabel, $recipients) {
            $summary = 'Lead recebido pelo formulário do site. Segmento: '.$data['segmento']
                .' | '.$rotuloPorte.': '.$data['porte']
                .' | WhatsApp: '.($data['phone'] ?? 'não informado');
            if (! empty($data['mensagem'])) {
                $summary .= "\n\nMensagem do visitante:\n".$data['mensagem'];
            }
            if (! empty($data['utm_campaign']) || ! empty($data['gclid'])) {
                $summary .= "\n\nOrigem do anúncio: ".($data['utm_source'] ?? 'google').' / '.($data['utm_campaign'] ?? 'sem campanha');
            }

            $lead = CrmLead::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'company_name' => $data['company'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['phone'] ?? null,
                'source' => 'site_'.$data['origem'],
                'stage' => CrmLead::STAGE_NOVO,
                'segment' => $data['segmento'],
                'company_size' => $data['porte'],
                'gclid' => $data['gclid'] ?? null,
                'gbraid' => $data['gbraid'] ?? null,
                'wbraid' => $data['wbraid'] ?? null,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'utm_term' => $data['utm_term'] ?? null,
                'utm_content' => $data['utm_content'] ?? null,
                'landing_url' => $data['landing_url'] ?? null,
            ]);

            // A interação exige um usuário; sem nenhum no tenant o lead ainda é salvo.
            if ($author = $recipients->first()) {
                CrmLeadInteraction::create([
                    'tenant_id' => $tenant->id,
                    'crm_lead_id' => $lead->id,
                    'user_id' => $author->id,
                    'channel' => 'Site — '.$origemLabel,
                    'contact_date' => now(),
                    'summary' => $summary,
                    'stage_at_time' => CrmLead::STAGE_NOVO,
                ]);
            }

            return $lead;
        });

        $this->notify($recipients, $lead, $rotuloPorte);

        return response()->json(['success' => true, 'id' => $lead->id], 201);
    }

    /**
     * Resolve o canal pelo token, comparando em tempo constante com TODOS os canais ligados.
     * É o ponto de troca para tokens por tenant guardados no banco.
     *
     * @return array{name: string, tenant_slug: string}|null
     */
    private function channelFor(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $found = null;
        foreach ((array) config('services.inbound.channels', []) as $name => $channel) {
            $expected = (string) ($channel['token'] ?? '');
            if ($expected !== '' && hash_equals($expected, $token)) {
                $found = ['name' => (string) $name, 'tenant_slug' => (string) ($channel['tenant_slug'] ?? ''), 'destination' => (string) ($channel['destination'] ?? 'crm')];
            }
        }

        return $found;
    }

    /**
     * Lead do site da PRÓPRIA Oravel: entra no funil de vendas da Central (SalesLead), com a mensagem
     * do visitante e a origem (página, anúncio, utm), e avisa só os super admins. Nunca cria nada num
     * tenant -- pedido do usuário 06/10/2026 ("não seja visível a nenhum tenant").
     */
    private function storeInCentral(array $data, string $rotuloPorte, string $origemLabel): JsonResponse
    {
        $superEmails = array_map('strtolower', config('oravel.super_admins', []));
        $author = User::withoutGlobalScopes()->whereIn(DB::raw('lower(email)'), $superEmails)->orderBy('created_at')->first();

        $niche = collect(Client::nicheLabels())->search(fn ($label, $key) => mb_strtolower($label) === mb_strtolower((string) $data['segmento']) || $key === $data['segmento']);

        $details = array_filter([
            'origem' => $data['origem'] ?? null,
            'origem_label' => $origemLabel,
            'segmento_informado' => $data['segmento'] ?? null,
            'porte_label' => $rotuloPorte,
            'porte' => $data['porte'] ?? null,
            'landing_url' => $data['landing_url'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'gbraid' => $data['gbraid'] ?? null,
            'wbraid' => $data['wbraid'] ?? null,
            'recebido_em' => now()->format('d/m/Y H:i'),
        ], fn ($v) => filled($v));

        // Quem foi o visitante: a visita ativa no site no instante do envio (cidade/estado, de onde veio...).
        $visit = SiteVisitMatcher::forMoment(now());
        $details = $visit
            ? array_merge($details, SiteVisitMatcher::details($visit, $data))
            : array_merge($details, array_filter(['veio_de' => ! empty($data['gclid']) || ! empty($data['utm_campaign']) ? 'Anúncio do Google'.(! empty($data['utm_campaign']) ? " (campanha {$data['utm_campaign']})" : '') : null]));

        $lead = DB::transaction(function () use ($data, $details, $niche, $author, $rotuloPorte, $origemLabel, $visit) {
            $lead = SalesLead::create([
                'city' => $visit?->city,
                'uf' => $visit?->state,
                'company_name' => $data['company'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'source' => SalesLead::SOURCE_SITE,
                'segment' => $niche === false ? null : $niche,
                'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                'decision_makers' => [['name' => $data['name'], 'role' => null]],
                'inbound_message' => $data['mensagem'] ?? null,
                'inbound_details' => $details,
            ]);

            if ($author) {
                $summary = 'Lead recebido pelo formulário do site. Segmento: '.$data['segmento']
                    .' | '.$rotuloPorte.': '.$data['porte']
                    .' | WhatsApp: '.($data['phone'] ?? 'não informado');
                if (! empty($data['mensagem'])) {
                    $summary .= "\n\nMensagem do visitante:\n".$data['mensagem'];
                }
                if (! empty($data['utm_campaign']) || ! empty($data['gclid'])) {
                    $summary .= "\n\nOrigem do anúncio: ".($data['utm_source'] ?? 'google').' / '.($data['utm_campaign'] ?? 'sem campanha');
                }

                SalesLeadInteraction::create([
                    'sales_lead_id' => $lead->id,
                    'user_id' => $author->id,
                    'channel' => 'Site — '.$origemLabel,
                    'contact_date' => now(),
                    'summary' => $summary,
                    'stage_at_time' => SalesLead::STAGE_PROSPECCAO,
                ]);
            }

            return $lead;
        });

        $this->notifyCentral($lead, $rotuloPorte, $superEmails);

        return response()->json(['success' => true, 'id' => $lead->id], 201);
    }

    /** Melhor esforço: o lead já está gravado. Só o sino da Central, só para super admins. */
    private function notifyCentral(SalesLead $lead, string $rotuloPorte, array $superEmails): void
    {
        try {
            $url = SalesLeadResource::getUrl('edit', ['record' => $lead], panel: 'central');
        } catch (\Throwable $e) {
            $url = null;
        }

        $recipients = User::withoutGlobalScopes()->whereIn(DB::raw('lower(email)'), $superEmails)->get();

        foreach ($recipients as $recipient) {
            try {
                $notification = Notification::make()
                    ->title('Novo lead do site')
                    ->body($lead->company_name.' — '.collect($lead->decision_makers)->pluck('name')->first().($lead->inbound_details['porte'] ?? null ? ' · '.$rotuloPorte.': '.$lead->inbound_details['porte'] : ''))
                    ->icon('heroicon-o-user-plus')
                    ->iconColor('success')
                    ->viewData(['scope' => 'central']);

                if ($url) {
                    $notification->actions([Action::make('abrir')->label('Abrir lead')->url($url)->markAsRead()]);
                }

                NotificationFacade::sendNow($recipient, $notification->toDatabase());
            } catch (\Throwable $e) {
                Log::warning('InboundLead: falha ao avisar a Central', ['user' => $recipient->id, 'erro' => $e->getMessage()]);
            }
        }
    }

    /** Quem recebe o aviso: administradores aprovados do tenant (ou, se não houver, os aprovados). */
    private function recipients(Tenant $tenant)
    {
        $users = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_approved', true)
            ->get();

        $admins = $users->filter(fn (User $user) => $user->isAdmin());

        return $admins->isNotEmpty() ? $admins->values() : $users->take(5)->values();
    }

    /** Melhor esforço: o lead já está gravado, uma falha aqui não pode devolver erro ao site. */
    private function notify($recipients, CrmLead $lead, string $rotuloPorte): void
    {
        try {
            $url = CrmLeadResource::getUrl('edit', ['record' => $lead], panel: 'admin');
        } catch (\Throwable $e) {
            $url = null;
        }

        // Tenant da própria operação do SaaS (Oravel): o aviso é só do sino da CENTRAL, para os
        // super admins -- nunca no sino do app (pedido do usuário 05/10/2026). Canais de outros
        // tenants seguem avisando os admins do tenant, como antes.
        $superEmails = array_map('strtolower', config('oravel.super_admins', []));
        $isOperatorTenant = $recipients->contains(fn (User $user) => in_array(strtolower((string) $user->email), $superEmails, true));

        foreach ($recipients as $recipient) {
            if ($isOperatorTenant && ! in_array(strtolower((string) $recipient->email), $superEmails, true)) {
                continue;
            }

            try {
                $notification = Notification::make()
                    ->title('Novo lead do site')
                    ->body($lead->company_name.' ('.$lead->name.') — '.$lead->segment.' · '.$rotuloPorte.': '.$lead->company_size)
                    ->icon('heroicon-o-user-plus')
                    ->iconColor('success');

                if ($isOperatorTenant) {
                    $notification->viewData(['scope' => 'central']);
                }

                if ($url) {
                    $notification->actions([
                        Action::make('abrir')->label('Abrir lead')->url($url)->markAsRead(),
                    ]);
                }

                $notification->sendToDatabase($recipient);
            } catch (\Throwable $e) {
                Log::warning('InboundLead: falha ao notificar usuário', ['user' => $recipient->id, 'erro' => $e->getMessage()]);
            }
        }
    }
}
