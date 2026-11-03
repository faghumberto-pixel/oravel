<?php

namespace App\Http\Controllers;

use App\Mail\MaterialGratuitoMail;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Landing de captura (isca): planilha de manutenção preventiva em troca de contato.
 * O lead entra no CRM Comercial da Central (SalesLead) com origem "isca-planilha-manutencao"
 * e a planilha segue por e-mail, em fila, como anexo (sem link público para o arquivo).
 */
class IscaPlanilhaController extends Controller
{
    public const ORIGEM = 'isca-planilha-manutencao';

    public function show(): View
    {
        return view('isca.planilha-manutencao');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: robô preenche o campo escondido; finge sucesso sem gravar nem enviar nada.
        if ($request->filled('website')) {
            return $this->sucesso((string) $request->input('email'));
        }

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 ()+\-.]{8,30}$/'],
            'company' => ['required', 'string', 'min:2', 'max:120'],
            'utm_source' => ['nullable', 'string', 'max:200'],
            'utm_medium' => ['nullable', 'string', 'max:200'],
            'utm_campaign' => ['nullable', 'string', 'max:200'],
            'gclid' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ], [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'phone.required' => 'Informe seu WhatsApp.',
            'phone.regex' => 'Informe o WhatsApp com DDD, só números.',
            'company.required' => 'Informe o nome da empresa.',
        ]);

        $this->registrarLead($dados);

        Mail::to($dados['email'])->queue(new MaterialGratuitoMail(self::ORIGEM, $dados['name']));

        return $this->sucesso($dados['email']);
    }

    private function sucesso(string $email): RedirectResponse
    {
        return redirect()->to(route('isca.planilha').'#baixar')->with('planilha_enviada', $email);
    }

    /** Grava o lead no CRM da Central; falha aqui nunca impede o envio da planilha. */
    private function registrarLead(array $d): void
    {
        try {
            DB::transaction(function () use ($d): void {
                $detalhes = array_filter([
                    'origem' => self::ORIGEM,
                    'origem_label' => 'Isca: planilha de manutenção preventiva',
                    'landing_url' => url('/planilha-manutencao-preventiva'),
                    'utm_source' => $d['utm_source'] ?? null,
                    'utm_medium' => $d['utm_medium'] ?? null,
                    'utm_campaign' => $d['utm_campaign'] ?? null,
                    'gclid' => $d['gclid'] ?? null,
                    'recebido_em' => now()->format('d/m/Y H:i'),
                ], fn ($v) => filled($v));

                // Mesmo e-mail pedindo de novo: reaproveita o lead em vez de duplicar.
                $lead = SalesLead::whereRaw('lower(email) = ?', [mb_strtolower($d['email'])])->first();

                if (! $lead) {
                    $lead = SalesLead::create([
                        'company_name' => $d['company'],
                        'email' => $d['email'],
                        'phone' => $d['phone'],
                        'source' => SalesLead::SOURCE_SITE,
                        'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
                        'decision_makers' => [['name' => $d['name'], 'role' => null]],
                        'inbound_details' => $detalhes,
                    ]);
                }

                $autor = User::withoutGlobalScopes()
                    ->whereIn(DB::raw('lower(email)'), array_map('strtolower', config('oravel.super_admins', [])))
                    ->orderBy('created_at')->first();

                if ($autor) {
                    SalesLeadInteraction::create([
                        'sales_lead_id' => $lead->id,
                        'user_id' => $autor->id,
                        'channel' => 'Site — Isca: planilha de manutenção preventiva',
                        'contact_date' => now(),
                        'summary' => 'Baixou a planilha de manutenção preventiva (origem '.self::ORIGEM.'). WhatsApp: '.$d['phone'],
                        'stage_at_time' => $lead->pipeline_stage,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Isca planilha: lead não gravado', ['erro' => $e->getMessage(), 'email' => $d['email']]);
        }
    }
}
