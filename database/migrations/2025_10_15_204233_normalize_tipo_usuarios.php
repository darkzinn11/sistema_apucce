<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class NormalizeTipoUsuarios extends Migration
{
    public function up()
    {
        // 1) cria coluna temporária string
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('tipo_tmp', 20)->nullable()->after('tipo');
        });

        // 2) copia e converte valores antigos para o formato novo
        //    admin -> ADMIN
        //    gestor -> FISCAL
        //    operador -> USER
        DB::statement("
            UPDATE usuarios SET tipo_tmp =
            CASE
                WHEN tipo = 'admin' THEN 'ADMIN'
                WHEN tipo = 'gestor' THEN 'FISCAL'
                WHEN tipo = 'operador' THEN 'USER'
                ELSE UPPER(tipo)
            END
        ");

        // 3) remove coluna antiga e recria como enum com os valores desejados
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->enum('tipo', ['ADMIN','FISCAL','USER'])->default('USER')->after('tipo_tmp');
        });

        // 4) copia volta para a nova enum e remove tmp
        DB::statement("UPDATE usuarios SET tipo = tipo_tmp");
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('tipo_tmp');
        });
    }

    public function down()
    {
        // volta para o estado anterior: enum('admin','gestor','operador')
        Schema::table('usuarios', function (Blueprint $table) {
            $table->enum('tipo', ['admin','gestor','operador'])->default('operador')->change();
        });
    }
}
