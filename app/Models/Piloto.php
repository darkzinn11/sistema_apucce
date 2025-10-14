<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Piloto extends Model
{
    protected $fillable = [
        'nome','cpf','email','telefone','data_nascimento',
        'tipo_sanguineo','foto_base64','cnh_frente_base64','cnh_verso_base64',
    ];
}
