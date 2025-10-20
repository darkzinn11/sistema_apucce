<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pilotos', function (Blueprint $table) {
            if (!Schema::hasColumn('pilotos','tipo_endereco')) $table->enum('tipo_endereco',['RESIDENCIAL','COMERCIAL'])->default('RESIDENCIAL')->nullable();
            if (!Schema::hasColumn('pilotos','cep'))          $table->string('cep',20)->nullable();
            if (!Schema::hasColumn('pilotos','numero'))       $table->string('numero',20)->nullable();
            if (!Schema::hasColumn('pilotos','logradouro'))   $table->string('logradouro',255)->nullable();
            if (!Schema::hasColumn('pilotos','complemento'))  $table->string('complemento',255)->nullable();
            if (!Schema::hasColumn('pilotos','bairro'))       $table->string('bairro',255)->nullable();
            if (!Schema::hasColumn('pilotos','cidade'))       $table->string('cidade',255)->nullable();
            if (!Schema::hasColumn('pilotos','uf'))           $table->string('uf',5)->nullable();
            if (!Schema::hasColumn('pilotos','pais'))         $table->string('pais',100)->nullable()->default('Brasil');
        });
    }

    public function down(): void
    {
        Schema::table('pilotos', function (Blueprint $table) {
            foreach (['tipo_endereco','cep','numero','logradouro','complemento','bairro','cidade','uf','pais'] as $col) {
                if (Schema::hasColumn('pilotos',$col)) $table->dropColumn($col);
            }
        });
    }
};
