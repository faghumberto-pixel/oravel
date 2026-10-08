<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrato como página para ver e imprimir (sem PDF): documento + comprovante
 * de assinatura quando já assinado. Duas entradas:
 *  - painel do cliente (administrador/equipe): Contract::query() já filtra o tenant
 *    e a policy decide quem pode ver;
 *  - Portal do Cliente (guard 'client'): resolve manualmente por tenant_id+client_id
 *    e responde 404 (não 403) quando não bate, para não revelar se o ID existe.
 */
class ContratoVisualizarController extends Controller
{
    public function painel(Request $request, string $contract): Response
    {
        $registro = Contract::query()->findOrFail($contract);

        abort_unless($request->user()?->can('view', $registro), 403);

        return $this->pagina($registro, \App\Filament\Resources\ContractResource::getUrl('index'));
    }

    public function portal(string $contract): Response
    {
        /** @var Client|null $client */
        $client = Auth::guard('client')->user();
        abort_unless($client, 403);

        $registro = Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('id', $contract)
            ->firstOrFail();

        return $this->pagina($registro, \App\Filament\Client\Pages\MeusContratos::getUrl(panel: 'portal-cliente'));
    }

    private function pagina(Contract $contrato, string $voltar): Response
    {
        $contrato->load(['client', 'asset']);
        $assinatura = $contrato->signedSignatures()->latest('signed_at')->first();

        $secoes = [[
            'titulo' => 'Contrato',
            'html' => view('pdf.contract', ['contract' => $contrato, 'generatedAt' => now()->format('d/m/Y H:i')])->render(),
        ]];

        if ($assinatura) {
            $secoes[] = [
                'titulo' => 'Comprovante de assinatura',
                'nova_pagina' => true,
                'html' => view('documents.signature-audit-page', [
                    'signature' => $assinatura,
                    'signerLocation' => $assinatura->geolocation
                        ? sprintf('Latitude: %s, Longitude: %s', $assinatura->geolocation['lat'] ?? 'N/A', $assinatura->geolocation['lng'] ?? 'N/A')
                        : 'Não capturada',
                ])->render(),
            ];
        }

        $resumo = ['Contrato' => $contrato->contract_number ?? '—', 'Cliente' => $contrato->client?->name ?? '—'];

        if ($assinatura) {
            $resumo += [
                'Assinado por' => $assinatura->signer_name,
                'CPF/CNPJ' => $assinatura->signer_document ?: '—',
                'Assinado em' => $assinatura->signed_at?->format('d/m/Y H:i'),
                'IP' => $assinatura->ip_address ?: '—',
                'Código de segurança' => $assinatura->document_hash ? substr($assinatura->document_hash, 0, 24).'…' : '—',
            ];
        } else {
            $resumo['Assinatura'] = 'Aguardando assinatura';
        }

        return response()->view('documentos.visualizar', [
            'titulo' => 'Contrato '.($contrato->contract_number ?? ''),
            'subtitulo' => $assinatura ? 'Contrato assinado e comprovante. Use Imprimir para papel ou para salvar em PDF pelo navegador.' : 'Contrato ainda sem assinatura. Use Imprimir para papel ou para salvar em PDF pelo navegador.',
            'voltar' => $voltar,
            'resumo' => $resumo,
            'secoes' => $secoes,
        ]);
    }
}
