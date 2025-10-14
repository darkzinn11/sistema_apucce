<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PilotoController;
use App\Http\Controllers\EnderecoController;
use App\Http\Controllers\CarroController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas públicas (login / health) + rotas protegidas por auth:api (JWT).
| Este arquivo cobre:
| - /api/db                           -> health check
| - /api/auth/login                   -> login público
| - /api/... (todas as rotas protegidas por auth:api)
|
*/

/** Health / DB ping (público) */
Route::get('/db', function () {
    try {
        $row = DB::select('SELECT NOW() as now');
        return response()->json(['db' => 'ok', 'now' => $row[0]->now]);
    } catch (\Throwable $e) {
        return response()->json(['db' => 'error', 'message' => $e->getMessage()], 500);
    }
});

/** Auth (público) */
Route::post('/auth/login', [AuthController::class, 'login']);

/** Rotas protegidas por JWT (auth:api) */
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Usuários (resource)
    Route::apiResource('usuarios', UsuarioController::class)->parameters([
        'usuarios' => 'usuario',
    ]);

    // Pilotos (resource padrão: index, store, show, update, destroy)
    Route::apiResource('pilotos', PilotoController::class);

    // Uploads específicos usados pelo frontend (multipart/form-data)
    // Ex.: POST /api/pilotos/{cpf}/cnh   (campo file)
    //       POST /api/pilotos/{cpf}/termo (campo file)
    Route::post('/pilotos/{cpf}/cnh', [PilotoController::class, 'uploadCnh']);
    Route::post('/pilotos/{cpf}/termo', [PilotoController::class, 'uploadTermo']);

    /**
     * Endereços
     * - POST /api/endereco            -> cria quando o CPF vem no body (frontend usa isso ao criar)
     * - POST /api/endereco/{cpf}      -> cria com CPF na URL (ou em edição)
     * - PUT  /api/endereco/{cpf}      -> atualiza
     * - GET  /api/endereco/{cpf}      -> busca
     */
    Route::post('/endereco', [EnderecoController::class, 'store']);
    Route::post('/endereco/{cpf}', [EnderecoController::class, 'store']);
    Route::put('/endereco/{cpf}', [EnderecoController::class, 'update']);
    Route::get('/endereco/{cpf}', [EnderecoController::class, 'show']);

    /**
     * Carros (veículo)
     * - POST /api/carros             -> cria (cpf via body)
     * - POST /api/carros/{cpf}       -> cria com cpf na URL
     * - PUT  /api/carros/{cpf}       -> atualiza
     * - GET  /api/carros/{cpf}       -> busca
     */
    Route::post('/carros', [CarroController::class, 'store']);
    Route::post('/carros/{cpf}', [CarroController::class, 'store']);
    Route::put('/carros/{cpf}', [CarroController::class, 'update']);
    Route::get('/carros/{cpf}', [CarroController::class, 'show']);
});
