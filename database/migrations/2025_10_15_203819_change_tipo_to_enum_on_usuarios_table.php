<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeTipoToEnumOnUsuariosTable extends Migration
{
    public function up()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // altera para enum com valores permitidos
            // requer doctrine/dbal para ->change()
            $table->enum('tipo', ['ADMIN', 'FISCAL', 'USER'])->default('USER')->change();
        });
    }

    public function down()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // volta para string curta, por exemplo
            $table->string('tipo', 10)->default('USER')->change();
        });
    }
}
