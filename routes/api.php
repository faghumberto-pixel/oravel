<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\WebhookAsaasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/**
 * Rota pública para verificação de saúde da aplicação.
 */
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/**
 * Rota pública que o Asaas vai acionar via POST (Gatilho de Webhooks)
 * Esta rota fica fora do middleware 'auth:sanctum' pois o Asaas precisa de acesso público.
 */
Route::post('/webhooks/asaas', [WebhookAsaasController::class, 'handle']);

/**
 * Grupo de rotas protegidas que exigem autenticação.
 */
Route::middleware('auth:sanctum')->group(function () {
    
    // Endpoint para buscar o checklist padrão baseado na categoria
    // Adicionado antes do resource para não entrar no conflito de IDs
    Route::get('assets/default-checklist/{category}', [AssetController::class, 'getDefaultChecklist']);

    // Rotas padrão REST (index, store, show, update, destroy)
    // Protege e gerencia os ativos cadastrados no ERP
    Route::apiResource('assets', AssetController::class);
    
});