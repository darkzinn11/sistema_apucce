<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/auth/login  (x-www-form-urlencoded: username, password)
    public function login(Request $req)
    {
        $email = $req->input('username');
        $password = $req->input('password');

        if (!$email || !$password) {
            return response()->json(['detail' => 'username e password são obrigatórios'], 422);
        }

        $user = Usuario::where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->senha_hash)) {
            return response()->json(['detail' => 'Credenciais inválidas'], 401);
        }

        // gera token JWT
        $token = JWTAuth::fromUser($user);

        // verifica se existe a coluna must_change_password e obtém o valor
        $mustChange = false;
        if (Schema::hasColumn('usuarios', 'must_change_password')) {
            $mustChange = (bool) ($user->must_change_password ?? false);
        }

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'tipo' => $user->tipo ?? $user->tipo_usuario ?? null,
                'is_active' => $user->is_active ?? true,
                'must_change_password' => $mustChange,
            ],
        ]);
    }

    // GET /api/me
    public function me()
    {
        $u = auth('api')->user();
        if (!$u) return response()->json(['detail' => 'Não autenticado'], 401);

        $mustChange = false;
        if (Schema::hasColumn('usuarios', 'must_change_password')) {
            $mustChange = (bool) ($u->must_change_password ?? false);
        }

        return response()->json([
            'id' => $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
            'tipo' => $u->tipo ?? $u->tipo_usuario ?? null,
            'is_active' => $u->is_active ?? true,
            'must_change_password' => $mustChange,
            'created_at' => $u->created_at,
            'updated_at' => $u->updated_at,
        ]);
    }

    // POST /api/auth/change-password
    // Payload: { current_password (opcional), new_password }
    public function changePassword(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) return response()->json(['message' => 'Não autenticado'], 401);

        // regras de validação: exigimos boa senha
        $rules = [
            'new_password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/'],
            'current_password' => ['sometimes','nullable','string'],
        ];

        $messages = [
            'new_password.required' => 'A nova senha é obrigatória.',
            'new_password.min' => 'A nova senha precisa ter ao menos 8 caracteres.',
            'new_password.regex' => 'A nova senha precisa conter letras maiúsculas, minúsculas, números e símbolo.',
        ];

        $data = $request->validate($rules, $messages);

        $needsCurrent = false;
        if (Schema::hasColumn('usuarios', 'must_change_password')) {
            $needsCurrent = (bool) ($user->must_change_password ?? false);
        }

        // Se o usuário NÃO está forçado a trocar, exigimos current_password
        if (!$needsCurrent) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $user->senha_hash)) {
                return response()->json(['message' => 'Senha atual inválida'], 422);
            }
        } else {
            // Se for forced-change e enviou current_password, podemos checar (opcional)
            if (!empty($data['current_password']) && !Hash::check($data['current_password'], $user->senha_hash)) {
                // não falha — apenas ignora, já que é forced flow. (seguir em frente)
            }
        }

        // usa o mutator do model, se existir (setSenhaAttribute) - basta atribuir 'senha'
        try {
            if (method_exists($user, 'setSenhaAttribute')) {
                $user->senha = $data['new_password'];
            } else {
                $user->senha_hash = Hash::make($data['new_password']);
            }

            if (Schema::hasColumn('usuarios', 'must_change_password')) {
                $user->must_change_password = false;
            }

            $user->save();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erro ao alterar senha', 'detail' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Senha alterada com sucesso']);
    }

    // POST /api/logout
    public function logout()
    {
        try { auth('api')->logout(); } catch (\Throwable $e) {}
        return response()->json(['message' => 'OK']);
    }
}
