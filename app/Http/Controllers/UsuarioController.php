<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    // Helper simples de permissão
    private function ensureAdmin()
    {
        $u = auth('api')->user();
        if (!$u || $u->tipo !== 'admin') {
            abort(response()->json(['detail' => 'Acesso negado'], 403));
        }
    }

    // GET /api/usuarios?search=&per_page=20
    public function index(Request $r)
    {
        $perPage = (int) $r->integer('per_page', 20);
        $perPage = max(1, min($perPage, 100)); // limita entre 1 e 100

        $q = Usuario::query()->select(['id','nome','email','tipo','created_at']);

        if ($s = $r->query('search')) {
            $s = trim($s);
            $q->where(function ($w) use ($s) {
                $w->where('nome', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%");
            });
        }

        return $q->orderBy('nome')->paginate($perPage);
    }

    // POST /api/usuarios
    public function store(Request $r)
    {
        $this->ensureAdmin(); // << Permissão: só admin

        $messages = [
            'email.unique' => 'Este e-mail já está em uso.',
            'senha.regex'  => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.',
        ];

        $data = $r->validate([
            'nome'   => ['required','string','max:255'],
            'email'  => ['required','email','max:255','unique:usuarios,email'],
            'senha'  => ['required','string','min:8','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/'],
            'tipo'   => ['nullable', Rule::in(['admin','gestor','operador'])],
        ], $messages);

        // normaliza email
        $email = strtolower(trim($data['email']));

        $u = Usuario::create([
            'nome'       => trim($data['nome']),
            'email'      => $email,
            'senha_hash' => Hash::make($data['senha']),
            'tipo'       => $data['tipo'] ?? 'operador',
        ]);

        return response()->json([
            'id'    => $u->id,
            'nome'  => $u->nome,
            'email' => $u->email,
            'tipo'  => $u->tipo,
            'created_at' => $u->created_at,
        ], 201);
    }

    // GET /api/usuarios/{usuario}
    public function show(Usuario $usuario)
    {
        return response()->json([
            'id'    => $usuario->id,
            'nome'  => $usuario->nome,
            'email' => $usuario->email,
            'tipo'  => $usuario->tipo,
            'created_at' => $usuario->created_at,
            'updated_at' => $usuario->updated_at,
        ]);
    }

    // PUT/PATCH /api/usuarios/{usuario}
    public function update(Request $r, Usuario $usuario)
    {
        $this->ensureAdmin(); // << Permissão: só admin

        $messages = [
            'email.unique' => 'Este e-mail já está em uso.',
            'senha.regex'  => 'A senha deve ter ao menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo.',
        ];

        $data = $r->validate([
            'nome'   => ['sometimes','string','max:255'],
            'email'  => ['sometimes','email','max:255', Rule::unique('usuarios','email')->ignore($usuario->id)],
            'senha'  => ['sometimes','string','min:8','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/'],
            'tipo'   => ['sometimes', Rule::in(['admin','gestor','operador'])],
        ], $messages);

        if (array_key_exists('nome', $data))  $usuario->nome  = trim($data['nome']);
        if (array_key_exists('email', $data)) $usuario->email = strtolower(trim($data['email']));
        if (array_key_exists('tipo', $data))  $usuario->tipo  = $data['tipo'];
        if (array_key_exists('senha', $data)) $usuario->senha_hash = Hash::make($data['senha']);

        $usuario->save();

        return response()->json([
            'id'    => $usuario->id,
            'nome'  => $usuario->nome,
            'email' => $usuario->email,
            'tipo'  => $usuario->tipo,
            'updated_at' => $usuario->updated_at,
        ]);
    }

    // DELETE /api/usuarios/{usuario}
    public function destroy(Usuario $usuario)
    {
        $this->ensureAdmin(); // << Permissão: só admin
        $usuario->delete();
        return response()->noContent();
    }
}
