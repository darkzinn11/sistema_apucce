<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToUsuariosTable extends Migration
{
    public function up()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'nome')) {
                $table->string('nome')->nullable()->after('id');
            }
            if (!Schema::hasColumn('usuarios', 'tipo_usuario')) {
                $table->string('tipo_usuario', 20)->default('USER')->after('email');
            }
            if (!Schema::hasColumn('usuarios', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('tipo_usuario');
            }
            // se você usa senha_hash já, não adiciona password. Caso não exista senha_hash:
            if (!Schema::hasColumn('usuarios', 'senha_hash')) {
                $table->string('senha_hash')->nullable()->after('email');
            }
        });
    }

    public function down()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'nome')) {
                $table->dropColumn('nome');
            }
            if (Schema::hasColumn('usuarios', 'tipo_usuario')) {
                $table->dropColumn('tipo_usuario');
            }
            if (Schema::hasColumn('usuarios', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('usuarios', 'senha_hash')) {
                // cuidado ao remover senha_hash em produção!
                //$table->dropColumn('senha_hash');
            }
        });
    }
}
