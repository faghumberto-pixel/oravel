<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\Crm\CnpjController;
use App\Http\Controllers\Api\Crm\ConfiguracaoController;
use App\Http\Controllers\Api\Crm\ContaController;
use App\Http\Controllers\Api\Crm\ContatoController;
use App\Http\Controllers\Api\Crm\EuController;
use App\Http\Controllers\Api\Crm\InteracaoController;
use App\Http\Controllers\Api\Crm\LeadController;
use App\Http\Controllers\Api\SignatureController;
use App\Http\Controllers\Api\V1\HourMeterPreloadController;
use App\Http\Controllers\Api\V1\HourMeterSyncController;
use App\Http\Controllers\Api\V1\TimeClockSyncController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\InboundLeadController;
use App\Http\Controllers\LandingPageLeadController;
use App\Http\Controllers\SiteTrackingController;
use App\Http\Controllers\WhatsAppEmpresaWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/*
 * Webhook do Asaas (cobrança da assinatura SaaS que cada Tenant paga pra
 * Oravel, não confundir com AccountPayable/AccountReceivable -- ver
 * docblock de AsaasWebhookController). Fora de auth:sanctum de propósito:
 * quem chama é a Asaas, não um usuário logado; autenticação é via header
 * 'asaas-access-token' comparado no próprio controller (mecanismo real
 * do Asaas -- token estático, não HMAC).
 */
Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle'])->name('asaas.webhook');

/*
 * Analytics do site institucional (oravel.com.br): recebe os eventos do script
 * public/t.js. Público de propósito (quem chama é o navegador do visitante do
 * site); só aceita Origin do próprio site e responde sempre 204 (ver
 * SiteTrackingController). Limite por IP contra abuso.
 */
Route::post('/site-track', [SiteTrackingController::class, 'collect'])
    ->middleware('throttle:240,1')
    ->name('site-track');

/*
 * Webhook do atendente virtual de WhatsApp (Meta Cloud API) -- atendimento
 * único da Oravel, não é por tenant. Fora de auth:sanctum de propósito:
 * quem chama é a plataforma da Meta, não um usuário logado. GET é o
 * handshake de verificação exigido ao cadastrar a URL do webhook; POST
 * recebe as mensagens de fato.
 */
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

// WhatsApp de CADA empresa (número próprio, API oficial da Meta): a empresa vai na URL.
// Webhook único do app da Oravel na Meta: serve todas as empresas (a empresa vem do número que recebeu).
Route::get('/webhooks/whatsapp-meta', [WhatsAppEmpresaWebhookController::class, 'verifyPlataforma']);
Route::post('/webhooks/whatsapp-meta', [WhatsAppEmpresaWebhookController::class, 'handlePlataforma'])->middleware('throttle:600,1');
// Preços das soluções para o site institucional (lidos da Central, sem dados de clientes).
Route::get('/public/planos', \App\Http\Controllers\PlanosPublicosController::class)->middleware('throttle:60,1')->name('planos.publicos');

// Asaas da EMPRESA cliente (cobrança automática dos clientes dela): empresa na URL, token por empresa no cabeçalho.
Route::post('/webhooks/asaas-empresa/{tenant}', [\App\Http\Controllers\AsaasEmpresaWebhookController::class, 'handle'])->middleware('throttle:600,1')->name('asaas.empresa.webhook');
Route::get('/webhooks/whatsapp-empresa/{tenant}', [WhatsAppEmpresaWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp-empresa/{tenant}', [WhatsAppEmpresaWebhookController::class, 'handle'])->middleware('throttle:600,1');

// Technician offline field app
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/technician/tasks-of-day', 'App\Http\Controllers\Api\TechnicianTasksController@tasksOfDay');
    Route::get('/health-check', 'App\Http\Controllers\Api\TechnicianTasksController@healthCheck');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('assets/default-checklist/{category}', [AssetController::class, 'getDefaultChecklist']);
    Route::apiResource('assets', AssetController::class);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/hour-meters/preload', [HourMeterPreloadController::class, 'index']);
    Route::post('/hour-meters/sync', [HourMeterSyncController::class, 'sync']);
    Route::post('/time-clocks/sync', [TimeClockSyncController::class, 'sync']);
});

// Entrada de leads vindos de formulários externos (site do tenant), servidor a servidor. Sem sessão:
// a barreira é o token do canal (X-Channel-Token, ver InboundLeadController) + throttle.
Route::post('/inbound/leads', [InboundLeadController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('inbound.leads.store');

// Landing page leads (sem auth - público)
Route::post('/landing-page/leads', [LandingPageLeadController::class, 'store']);

// Assinatura eletrônica SLA + LGPD (sem auth - público)
Route::post('/signatures', [SignatureController::class, 'store']);

/*
 * API do CRM por token pessoal (extensão do WhatsApp e integrações) -- ver docs/api/crm-v1.md.
 * Token emitido só dentro do painel (Comercial > Gestão CRM > Integração / API), sem login por senha.
 * crm.api: habilidade do token, usuário aprovado, conta em dia e módulo de CRM liberado.
 */
Route::prefix('crm/v1')->middleware('auth:sanctum')->group(function () {
    Route::middleware(['crm.api:crm:read', 'throttle:crm-api-leitura'])->group(function () {
        Route::get('/eu', EuController::class);
        Route::get('/configuracao', ConfiguracaoController::class);
        Route::get('/contatos/buscar', [ContatoController::class, 'buscar']);
        Route::get('/contatos/{tipo}/{id}/oportunidades', [ContatoController::class, 'oportunidades']);
        Route::get('/cnpj/{cnpj}', CnpjController::class);
        Route::get('/leads/{id}', [LeadController::class, 'show']);
        Route::get('/contas/{id}', [ContaController::class, 'show']);
        Route::get('/produtos', [ContaController::class, 'produtos']);
    });

    Route::middleware(['crm.api:crm:write', 'throttle:crm-api-escrita'])->group(function () {
        Route::post('/leads', [LeadController::class, 'store']);
        Route::patch('/leads/{id}/etapa', [LeadController::class, 'etapa']);
        Route::post('/leads/{id}/interacoes', [InteracaoController::class, 'store']);
    });
});
