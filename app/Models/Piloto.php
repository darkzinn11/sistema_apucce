<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Piloto extends Model
{
    // Ajuste os nomes das colunas conforme seu banco. Incluí possíveis nomes.
    protected $fillable = [
        'nome',
        'cpf',
        'cpf_piloto',
        'email',
        'email_piloto',
        'telefone',
        'numero_telefone',
        'data_nascimento',
        'tipo_sanguineo',
        'foto_base64',
        'foto_piloto',
        'foto_cnh',
        'cnh_frente_base64',
        'cnh_verso_base64',
        'termo_adesao',
        'usuario_id',     // opcional: FK para usuarios.id (se existir)
        'email_usuario',  // opcional: alternativa para linkar usuário pelo email
        'id_piloto',
    ];

    /**
     * Relação (opcional) com Usuario, se a coluna usuario_id existir.
     * Ajuste se você usar outro FK.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'usuario_id', 'id');
    }
}
