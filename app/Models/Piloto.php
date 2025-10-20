<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Piloto extends Model
{
    protected $table = 'pilotos';

    protected $fillable = [
        'id_piloto',
        'usuario_id',
        'email_usuario',

        'nome_piloto',
        'cpf_piloto',
        'email_piloto',
        'numero_telefone',
        'data_nascimento',
        'tipo_sanguineo',

        'nome_contato_seguranca',
        'numero_contato_seguranca',
        'nome_plano_saude',

        'foto_piloto',
        'foto_piloto_tipo',
        'foto_cnh',
        'foto_cnh_tipo',
        'termo_adesao',
        'termo_adesao_tipo',

        'tipo_endereco',
        'cep',
        'numero',
        'logradouro',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'pais',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'usuario_id', 'id');
    }
}
