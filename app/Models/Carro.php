<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carro extends Model
{
    use HasFactory;

    protected $fillable = [
        'cpf_piloto', 'foto_frente', 'foto_tras', 
        'foto_esquerda', 'foto_direita', 'nota_fiscal'
    ];
}
