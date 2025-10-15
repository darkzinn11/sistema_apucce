<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUsuarioIdToPilotosTable extends Migration
{
    public function up()
    {
        Schema::table('pilotos', function (Blueprint $table) {
            // nullable porque pilotos existentes podem não ter usuario vinculado
            $table->unsignedBigInteger('usuario_id')->nullable()->after('id');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('pilotos', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropColumn('usuario_id');
        });
    }
}
