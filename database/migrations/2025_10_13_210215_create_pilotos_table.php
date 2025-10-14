<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pilotos', function (Blueprint $table) {
            $table->id();
            $table->string('cpf_piloto')->unique();
            $table->string('nome_piloto');
            $table->string('email_piloto')->nullable();
            $table->string('numero_telefone')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->integer('estado_civil')->nullable();
            $table->integer('tipo_sanguineo')->nullable();
            $table->string('nome_contato_seguranca')->nullable();
            $table->string('numero_contato_seguranca')->nullable();
            $table->string('nome_plano_saude')->nullable();
            $table->longText('foto_piloto')->nullable();
            $table->longText('foto_cnh')->nullable();
            $table->string('foto_cnh_tipo')->nullable();
            $table->longText('termo_adesao')->nullable();
            $table->string('termo_adesao_tipo')->nullable();
            $table->string('id_piloto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('pilotos');
    }
};
