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
    Route::get('/pilotos/email/{email}', [\App\Http\Controllers\PilotoController::class, 'showByEmail']);


    // Troca de senha (usuário autenticado muda a própria senha)
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Usuários (resource)
    Route::apiResource('usuarios', UsuarioController::class)->parameters([
        'usuarios' => 'usuario',
    ]);

    // Pilotos (resource padrão: index, store, show, update, destroy)
    Route::apiResource('pilotos', PilotoController::class);

    // Uploads específicos usados pelo frontend (multipart/form-data)
    Route::post('/pilotos/{cpf}/cnh', [PilotoController::class, 'uploadCnh']);
    Route::post('/pilotos/{cpf}/termo', [PilotoController::class, 'uploadTermo']);

    // Endereço
    Route::post('/endereco', [EnderecoController::class, 'store']);
    Route::post('/endereco/{cpf}', [EnderecoController::class, 'store']);
    Route::put('/endereco/{cpf}', [EnderecoController::class, 'update']);
    Route::get('/endereco/{cpf}', [EnderecoController::class, 'show']);

    // Carros
    Route::post('/carros', [CarroController::class, 'store']);
    Route::post('/carros/{cpf}', [CarroController::class, 'store']);
    Route::put('/carros/{cpf}', [CarroController::class, 'update']);
    Route::get('/carros/{cpf}', [CarroController::class, 'show']);
});
