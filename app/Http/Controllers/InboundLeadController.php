<?php

namespace App\Http\Controllers;

use App\Filament\Resources\CrmLeadResource;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $recipients = $this->recipients($tenant);

        $lead = DB::transaction(function () use ($tenant, $data, $rotuloPorte, $origemLabel, $recipients) {
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
            ]);

            // A interação exige um usuário; sem nenhum no tenant o lead ainda é salvo.
            if ($author = $recipients->first()) {
                CrmLeadInteraction::create([
                    'tenant_id' => $tenant->id,
                    'crm_lead_id' => $lead->id,
                    'user_id' => $author->id,
                    'channel' => 'Site — '.$origemLabel,
                    'contact_date' => now(),
                    'summary' => 'Lead recebido pelo formulário do site. Segmento: '.$data['segmento']
                        .' | '.$rotuloPorte.': '.$data['porte']
                        .' | WhatsApp: '.($data['phone'] ?? 'não informado'),
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
                $found = ['name' => (string) $name, 'tenant_slug' => (string) ($channel['tenant_slug'] ?? '')];
            }
        }

        return $found;
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

        foreach ($recipients as $recipient) {
            try {
                $notification = Notification::make()
                    ->title('Novo lead do site')
                    ->body($lead->company_name.' ('.$lead->name.') — '.$lead->segment.' · '.$rotuloPorte.': '.$lead->company_size)
                    ->icon('heroicon-o-user-plus')
                    ->iconColor('success');

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
