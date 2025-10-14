<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // POST /api/auth/login  (x-www-form-urlencoded: username, password)
    public function login(Request $req)
    {
        $email = $req->input('username');
        $password = $req->input('password');

        $user = Usuario::where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->senha_hash)) {
            return response()->json(['detail' => 'Credenciais inválidas'], 401);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id'    => $user->id,
                'nome'  => $user->nome,
                'email' => $user->email,
                'tipo'  => $user->tipo,
            ],
        ]);
    }

    // GET /api/me
    public function me()
    {
        $u = auth('api')->user();
        if (!$u) return response()->json(['detail' => 'Não autenticado'], 401);
        return response()->json($u);
    }

    // POST /api/logout
    public function logout()
    {
        try { auth('api')->logout(); } catch (\Throwable $e) {}
        return response()->json(['message' => 'OK']);
    }
}
