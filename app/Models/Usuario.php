<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuarios';

    protected $fillable = [
        'nome', 'email', 'senha_hash', 'tipo',
    ];

    protected $hidden = [
        'senha_hash',
    ];

    // Faz o Laravel usar 'senha_hash' como "password"
    public function getAuthPassword()
    {
        return $this->senha_hash;
    }

    // ==== Métodos exigidos pelo JWTSubject ====
    public function getJWTIdentifier()
    {
        return $this->getKey(); // normalmente o ID
    }

    public function getJWTCustomClaims()
    {
        // você pode adicionar claims extras aqui se quiser
        return [];
    }
}
