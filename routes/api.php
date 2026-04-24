<?php

use App\Http\Controllers\Api\AssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aqui registramos as rotas da nossa API. Estas rotas são
| automaticamente prefixadas com /api.
|
*/

/**
 * Rota pública para verificação de saúde da aplicação.
 */
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/**
 * Grupo de rotas protegidas que exigem autenticação.
 * Todas as rotas de negócio da Oravel devem estar aqui dentro.
 */
Route::middleware('auth:sanctum')->group(function () {
    // Usamos 'assets' (minúsculo e plural) conforme padrão RESTful
    Route::apiResource('assets', AssetController::class);
    
    // As próximas rotas de negócio (ex: order-services) serão adicionadas aqui
});