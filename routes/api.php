<?php

use App\Http\Controllers\Api\AssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/**
 * TODO (integração financeira - módulo "financial"):
 * Webhook do Asaas ainda não implementado. O controller
 * App\Http\Controllers\WebhookAsaasController não existe no projeto.
 * Antes de reativar esta rota:
 *   1. Criar o WebhookAsaasController com validação de assinatura do Asaas;
 *   2. Adicionar credenciais/segredo do Asaas em config/services.php e .env;
 *   3. Tratar os eventos de cobrança e atualizar AccountPayable.
 *
 * Route::post('/webhooks/asaas', [WebhookAsaasController::class, 'handle']);
 */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('assets/default-checklist/{category}', [AssetController::class, 'getDefaultChecklist']);
    Route::apiResource('assets', AssetController::class);
});
