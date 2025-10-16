<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class UsuarioController extends Controller
{
    /**
     * Verifica se o usuário autenticado é administrador.
     * Aceita tanto 'ADMIN' quanto 'admin' e também checa 'tipo_usuario'.
     */
    private function ensureAdmin()
    {
        $u = auth('api')->user();
        if (!$u) {
            abort(response()->json(['detail' => 'Acesso negado'], 403));
        }

        $role = null;
        if (isset($u->tipo)) $role = $u->tipo;
        if (empty($role) && isset($u->tipo_usuario)) $role = $u->tipo_usuario;

        $role = strtolower((string)$role);
        if ($role !== 'admin' && $role !== 'ADMIN' && $role !== 'administrator') {
            // tolerate different naming by checking lowercase
            if ($role !== 'admin') {
                abort(response()->json(['detail' => 'Acesso negado'], 403));
            }
        }
    }

    /**
     * GET /api/usuarios?search=&per_page=20
     */
    public function index(Request $r)
    {
        $this->ensureAdmin();

        $perPage = (int) $r->integer('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        // Seleciona colunas que comumente existem; suporta variações
        $select = ['id', 'nome', 'email', 'created_at'];
        if (Schema::hasColumn('usuarios', 'tipo')) $select[] = 'tipo';
        if (Schema::hasColumn('usuarios', 'tipo_usuario')) $select[] = 'tipo_usuario';
        if (Schema::hasColumn('usuarios', 'is_active')) $select[] = 'is_active';
        if (Schema::hasColumn('usuarios', 'must_change_password')) $select[] = 'must_change_password';

        $q = Usuario::query()->select($select);

        if ($s = $r->query('search')) {
            $s = trim($s);
            $q->where(function ($w) use ($s) {
                $w->where('nome', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%");
            });
        }

        return $q->orderBy('nome')->paginate($perPage);
    }

    /**
     * POST /api/usuarios
     * Se `senha` não for enviada, usa DEFAULT_USER_PASSWORD do .env (se existir),
     * senão gera uma senha temporária aleatória. Quando a senha foi gerada/automática,
     * marca must_change_password = true e retorna temp_password no response (apenas para admin).
     */
    public function store(Request $r)
    {
        $this->ensureAdmin();

        $messages = [
            'email.unique' => 'Este e-mail já está em uso.',
            'senha.regex'  => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.',
        ];

        // senha agora é opcional; se não vier usaremos DEFAULT_USER_PASSWORD ou gerada
        $data = $r->validate([
            'nome'   => ['required','string','max:255'],
            'email'  => ['required','email','max:255','unique:usuarios,email'],
            'senha'  => ['sometimes','nullable','string','min:8','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/'],
            'tipo'   => ['sometimes','nullable'],
            'tipo_usuario' => ['sometimes','nullable'],
            'is_active' => ['sometimes','boolean'],
        ], $messages);

        $email = strtolower(trim($data['email']));

        // Decide qual senha usar
        $providedPassword = $data['senha'] ?? null;
        $usedGeneratedPassword = false;
        $tempPassword = null;

        if (!$providedPassword) {
            // tenta pegar do .env
            $default = env('DEFAULT_USER_PASSWORD', null);
            if ($default) {
                $tempPassword = $default;
            } else {
                // gera senha aleatória segura
                $tempPassword = Str::random(12) . '1!A'; // garante complexidade
            }
            $usedGeneratedPassword = true;
        }

        $finalPassword = $providedPassword ?? $tempPassword;

        // Normalize role value - allow either 'tipo' or 'tipo_usuario'
        $role = $data['tipo'] ?? $data['tipo_usuario'] ?? null;
        if ($role) $role = strtoupper($role);
        // map common synonyms to ADMIN/FISCAL/USER if needed (optional)
        $allowedRoles = ['ADMIN','FISCAL','USER','ADMINISTRADOR','GESTOR','OPERADOR'];
        if ($role && !in_array($role, $allowedRoles)) {
            // try to map lowercase forms
            $rLow = strtolower($role);
            if ($rLow === 'admin' || $rLow === 'administrador') $role = 'ADMIN';
            elseif ($rLow === 'fiscal') $role = 'FISCAL';
            elseif ($rLow === 'user' || $rLow === 'operador' || $rLow === 'gestor') $role = 'USER';
            else $role = 'USER';
        } elseif (!$role) {
            $role = 'USER';
        }

        // Prepara dados do usuário
        $userData = [
            'nome' => trim($data['nome']),
            'email' => $email,
            // use o mutator 'senha' presente no Model (ele fará Hash::make)
            'senha' => $finalPassword,
            // tente popular ambas colunas por compatibilidade
            'tipo' => $role,
            'tipo_usuario' => $role,
            'is_active' => $data['is_active'] ?? true,
        ];

        // se a coluna must_change_password existir e a senha foi gerada/automática, set true
        if (Schema::hasColumn('usuarios', 'must_change_password') && $usedGeneratedPassword) {
            $userData['must_change_password'] = true;
        }

        // cria usuário
        $u = Usuario::create($userData);

        $response = [
            'id' => $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
            'tipo' => $u->tipo ?? $u->tipo_usuario ?? null,
            'is_active' => $u->is_active ?? true,
            'must_change_password' => $u->must_change_password ?? false,
            'created_at' => $u->created_at,
        ];

        // só devolve a senha temporária quando gerada (pois o admin precisa repassá-la ao usuário)
        if ($usedGeneratedPassword) {
            $response['temp_password'] = $tempPassword;
            $response['note'] = 'Senha temporária retornada apenas para o administrador. Em produção, entregue-a por canal seguro ou obrigue reset por email.';
        }

        return response()->json($response, 201);
    }

    /**
     * GET /api/usuarios/{usuario}
     */
    public function show(Usuario $usuario)
    {
        return response()->json([
            'id'    => $usuario->id,
            'nome'  => $usuario->nome,
            'email' => $usuario->email,
            'tipo'  => $usuario->tipo ?? $usuario->tipo_usuario ?? null,
            'is_active' => $usuario->is_active ?? true,
            'must_change_password' => $usuario->must_change_password ?? false,
            'created_at' => $usuario->created_at,
            'updated_at' => $usuario->updated_at,
        ]);
    }

    /**
     * PUT/PATCH /api/usuarios/{usuario}
     */
    public function update(Request $r, Usuario $usuario)
    {
        $this->ensureAdmin();

        $messages = [
            'email.unique' => 'Este e-mail já está em uso.',
            'senha.regex'  => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.',
        ];

        $data = $r->validate([
            'nome'   => ['sometimes','string','max:255'],
            'email'  => ['sometimes','email','max:255', Rule::unique('usuarios','email')->ignore($usuario->id)],
            'senha'  => ['sometimes','nullable','string','min:8','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/'],
            'tipo'   => ['sometimes','nullable'],
            'tipo_usuario' => ['sometimes','nullable'],
            'is_active' => ['sometimes','boolean'],
            'must_change_password' => ['sometimes','boolean'], // admin pode forçar
        ], $messages);

        if (array_key_exists('nome', $data))  $usuario->nome  = trim($data['nome']);
        if (array_key_exists('email', $data)) $usuario->email = strtolower(trim($data['email']));

        // role normalization
        $role = $data['tipo'] ?? $data['tipo_usuario'] ?? null;
        if ($role) {
            $rUp = strtoupper($role);
            if (in_array($rUp, ['ADMIN','FISCAL','USER','ADMINISTRADOR','GESTOR','OPERADOR'])) {
                // map synonyms
                if (in_array($rUp, ['ADMIN','ADMINISTRADOR'])) $rUp = 'ADMIN';
                elseif ($rUp === 'FISCAL') $rUp = 'FISCAL';
                else $rUp = 'USER';
                // attempt to set both fields if they exist
                if (Schema::hasColumn('usuarios','tipo')) $usuario->tipo = $rUp;
                if (Schema::hasColumn('usuarios','tipo_usuario')) $usuario->tipo_usuario = $rUp;
            }
        }

        if (array_key_exists('is_active', $data) && Schema::hasColumn('usuarios','is_active')) {
            $usuario->is_active = (bool) $data['is_active'];
        }

        // senha: se enviada, atualiza e, por padrão, limpa must_change_password = false
        if (array_key_exists('senha', $data) && $data['senha']) {
            // usar o mutator 'senha' (atribuição direta) para garantir hashing
            $usuario->senha = $data['senha'];
            if (Schema::hasColumn('usuarios','must_change_password')) {
                $usuario->must_change_password = false;
            }
        }

        // se admin passou must_change_password explicitamente, respeitar
        if (array_key_exists('must_change_password', $data) && Schema::hasColumn('usuarios','must_change_password')) {
            $usuario->must_change_password = (bool) $data['must_change_password'];
        }

        $usuario->save();

        return response()->json([
            'id'    => $usuario->id,
            'nome'  => $usuario->nome,
            'email' => $usuario->email,
            'tipo'  => $usuario->tipo ?? $usuario->tipo_usuario ?? null,
            'is_active' => $usuario->is_active ?? true,
            'must_change_password' => $usuario->must_change_password ?? false,
            'updated_at' => $usuario->updated_at,
        ]);
    }

    /**
     * DELETE /api/usuarios/{usuario}
     */
    public function destroy(Usuario $usuario)
    {
        $this->ensureAdmin();
        $usuario->delete();
        return response()->noContent();
    }
}
