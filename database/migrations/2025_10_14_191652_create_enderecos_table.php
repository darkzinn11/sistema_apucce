<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('enderecos', function (Blueprint $table) {
            $table->id();
            $table->string('cpf_piloto');
            $table->string('tipo_endereco')->default('RESIDENCIAL');
            $table->string('cep')->nullable();
            $table->string('logradouro')->nullable();
            $table->integer('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('pais')->default('Brasil');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('enderecos');
    }
};
