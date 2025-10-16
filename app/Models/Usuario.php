<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuarios';

    /**
     * Campos que podem ser preenchidos em massa.
     * Incluí tanto 'tipo' quanto 'tipo_usuario' para compatibilidade.
     * Remova o que não existir no seu banco de dados.
     */
    protected $fillable = [
        'nome',
        'email',
        'senha_hash',
        'senha',            // permite atribuir $usuario->senha = 'texto' (mutator irá transformar)
        'tipo',             // legacy (se sua tabela usar 'tipo')
        'tipo_usuario',     // alternativa: 'tipo_usuario'
        'is_active',
        'must_change_password',
        'email_verified_at',
    ];

    /**
     * Campos escondidos quando o model é serializado (JSON).
     */
    protected $hidden = [
        'senha_hash',
        'remember_token',
    ];

    /**
     * Casts (conversões automáticas).
     */
    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /**
     * Use 'senha_hash' como campo de autenticação (Laravel chama getAuthPassword()).
     */
    public function getAuthPassword()
    {
        return $this->senha_hash;
    }

    /**
     * Mutator: ao definir $usuario->senha = 'minhaSenha', gravamos como senha_hash.
     * Também detecta strings já hashed (padrão bcrypt $2y$...).
     */
    public function setSenhaAttribute(?string $value)
    {
        if ($value === null) {
            return;
        }

        // se já parece uma hash bcrypt (60 chars e começa com $2y$/$2a$/$2b$), grava direto
        if (is_string($value) && strlen($value) === 60 && preg_match('/^\$2[ayb]\$/', $value)) {
            $this->attributes['senha_hash'] = $value;
        } else {
            $this->attributes['senha_hash'] = Hash::make($value);
        }
    }

    /**
     * Helper legível: se o usuario deve trocar a senha no próximo login.
     */
    public function needsPasswordChange(): bool
    {
        return (bool) ($this->must_change_password ?? false);
    }

    /**
     * Relação com Piloto (caso exista FK usuario_id na tabela pilotos).
     */
    public function piloto(): HasOne
    {
        return $this->hasOne(\App\Models\Piloto::class, 'usuario_id', 'id');
    }

    // ==== Métodos exigidos pelo JWTSubject ====
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        // retornamos role tanto de 'tipo' quanto 'tipo_usuario' para compatibilidade
        $role = $this->tipo_usuario ?? $this->tipo ?? null;
        return [
            'role' => $role,
            'email' => $this->email ?? null,
        ];
    }
}
