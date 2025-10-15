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
     * Adicione ou remova conforme suas colunas reais.
     */
    protected $fillable = [
        'nome',
        'email',
        'senha_hash',
        'tipo',        // ADMIN | FISCAL | USER
        'is_active',   // opcional: se existir na tabela
        'email_verified_at', // opcional
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
     * Mutator de conveniência: se alguém setar $usuario->senha = 'texto',
     * armazenamos automaticamente como senha_hash com Hash::make.
     *
     * Observação: se você já fizer Hash::make na controller antes de criar,
     * isso só será aplicado se você atribuir 'senha' diretamente.
     */
    public function setSenhaAttribute(?string $value)
    {
        if ($value === null) return;
        // evita re-hash de uma hash já passada por engano:
        if (strlen($value) === 60 && preg_match('/^\$2y\$/', $value)) {
            $this->attributes['senha_hash'] = $value;
        } else {
            $this->attributes['senha_hash'] = Hash::make($value);
        }
    }

    /**
     * Relação com Piloto (caso pilote tenha usuario_id FK).
     * Ajuste o namespace se necessário.
     */
    public function piloto(): HasOne
    {
        return $this->hasOne(\App\Models\Piloto::class, 'usuario_id', 'id');
    }

    // ==== Métodos exigidos pelo JWTSubject ====
    public function getJWTIdentifier()
    {
        return $this->getKey(); // normalmente o ID
    }

    public function getJWTCustomClaims()
    {
        // Inclui role/email para facilitar controle no frontend (opcional)
        return [
            'role' => $this->tipo ?? null,
            'email' => $this->email ?? null,
        ];
    }
}
