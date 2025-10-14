<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carros', function (Blueprint $table) {
            $table->id();
            $table->string('cpf_piloto');
            $table->longText('foto_frente')->nullable();
            $table->longText('foto_tras')->nullable();
            $table->longText('foto_esquerda')->nullable();
            $table->longText('foto_direita')->nullable();
            $table->longText('nota_fiscal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('carros');
    }
};
