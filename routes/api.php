<?php

use App\Http\Controllers\Api\AssetController;
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
 * Grupo de rotas protegidas que exigem autenticação.
 */
Route::middleware('auth:sanctum')->group(function () {
    
    // Endpoint para buscar o checklist padrão baseado na categoria
    // Adicionado antes do resource para não entrar no conflito de IDs
    Route::get('assets/default-checklist/{category}', [AssetController::class, 'getDefaultChecklist']);

    // Rotas padrão REST (index, store, show, update, destroy)
    Route::apiResource('assets', AssetController::class);
    
});
