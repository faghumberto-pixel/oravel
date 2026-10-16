<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadInteraction;
use App\Support\CrmApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

class InteracaoController extends Controller
{
    use RespondeCrmApi;

    public function store(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $lead = CrmApiPresenter::leadsVisiveis($user)->find($id);

        if (! $lead) {
            return $this->erro('nao_encontrado', 'Lead não encontrado.', 404);
        }

        Gate::forUser($user)->authorize('create', CrmLeadInteraction::class);

        $d = $request->validate([
            'canal' => ['nullable', Rule::in(array_keys(CrmLeadInteraction::channelLabels()))],
            'direcao' => ['nullable', Rule::in(array_keys(CrmLeadInteraction::direcaoLabels()))],
            'resumo' => ['required_without:audio', 'nullable', 'string', 'max:5000'],
            'transcricao' => ['nullable', 'string', 'max:20000'],
            'proximo_passo' => ['nullable', 'string', 'max:2000'],
            'proximo_contato' => ['nullable', 'date', 'after_or_equal:today'],
            'audio' => ['nullable', 'file', 'max:16384', 'mimetypes:audio/ogg,audio/opus,audio/mpeg,audio/mp4,audio/x-m4a,audio/webm,video/webm,audio/wav,audio/x-wav'],
        ]);

        $interacao = CrmLeadInteraction::create([
            'tenant_id' => $user->tenant_id,
            'crm_lead_id' => $lead->id,
            'user_id' => $user->id,
            'channel' => $d['canal'] ?? CrmLeadInteraction::CHANNEL_WHATSAPP,
            'direcao' => $d['direcao'] ?? null,
            'contact_date' => now(),
            'summary' => $d['resumo'] ?? 'Áudio registrado pelo WhatsApp.',
            'transcricao' => $d['transcricao'] ?? null,
            'next_action' => $d['proximo_passo'] ?? null,
            'next_followup_date' => $d['proximo_contato'] ?? null,
            'stage_at_time' => $lead->stage,
        ]);

        if ($request->hasFile('audio')) {
            try {
                $interacao->addMediaFromRequest('audio')->toMediaCollection('audio');
            } catch (FileUnacceptableForCollection) {
                // Conteúdo que não é áudio de verdade (o tipo declarado pelo cliente pode mentir): desfaz a interação.
                $interacao->delete();

                throw ValidationException::withMessages(['audio' => 'O arquivo enviado não é um áudio aceito.']);
            }
        }

        return $this->ok(CrmApiPresenter::interacao($interacao->refresh()), 201);
    }
}
