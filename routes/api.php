<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\V1\HourMeterPreloadController;
use App\Http\Controllers\Api\V1\HourMeterSyncController;
use App\Http\Controllers\Api\V1\TimeClockSyncController;
use App\Http\Controllers\AsaasWebhookController;
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
Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle']);

/*
 * Webhook do atendente virtual de WhatsApp (Meta Cloud API) -- atendimento
 * único da Oravel, não é por tenant. Fora de auth:sanctum de propósito:
 * quem chama é a plataforma da Meta, não um usuário logado. GET é o
 * handshake de verificação exigido ao cadastrar a URL do webhook; POST
 * recebe as mensagens de fato.
 */
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

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
