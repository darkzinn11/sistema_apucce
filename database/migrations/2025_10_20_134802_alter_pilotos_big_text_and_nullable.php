<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pilotos', function (Blueprint $table) {
            // textos grandes (base64 ou caminhos/URLs)
            if (Schema::hasColumn('pilotos', 'foto_piloto')) {
                $table->longText('foto_piloto')->nullable()->change();
            }
            if (Schema::hasColumn('pilotos', 'foto_cnh')) {
                $table->longText('foto_cnh')->nullable()->change();
            }
            if (Schema::hasColumn('pilotos', 'termo_adesao')) {
                $table->longText('termo_adesao')->nullable()->change();
            }

            // tipos MIME
            if (Schema::hasColumn('pilotos', 'foto_piloto_tipo')) {
                $table->string('foto_piloto_tipo', 50)->nullable()->default(null)->change();
            } else {
                $table->string('foto_piloto_tipo', 50)->nullable()->default(null);
            }
            if (Schema::hasColumn('pilotos', 'foto_cnh_tipo')) {
                $table->string('foto_cnh_tipo', 50)->nullable()->default(null)->change();
            } else {
                $table->string('foto_cnh_tipo', 50)->nullable()->default(null);
            }
            if (Schema::hasColumn('pilotos', 'termo_adesao_tipo')) {
                $table->string('termo_adesao_tipo', 50)->nullable()->default(null)->change();
            } else {
                $table->string('termo_adesao_tipo', 50)->nullable()->default(null);
            }

            // dados pessoais com NULL permitido
            if (Schema::hasColumn('pilotos', 'estado_civil')) {
                $table->integer('estado_civil')->nullable()->default(null)->change();
            }
            if (Schema::hasColumn('pilotos', 'tipo_sanguineo')) {
                $table->integer('tipo_sanguineo')->nullable()->default(null)->change();
            }
            if (Schema::hasColumn('pilotos', 'data_nascimento')) {
                $table->date('data_nascimento')->nullable()->default(null)->change();
            }

            // contatos e plano de saúde
            foreach (['nome_contato_seguranca','numero_contato_seguranca','nome_plano_saude'] as $col) {
                if (Schema::hasColumn('pilotos', $col)) {
                    $table->string($col, 255)->nullable()->default(null)->change();
                }
            }

            // compat/legados se existirem: deixe grande
            foreach (['foto_base64','cnh_frente_base64','cnh_verso_base64'] as $col) {
                if (Schema::hasColumn('pilotos', $col)) {
                    $table->longText($col)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        // Não tente reverter para tamanhos menores (pode truncar dados).
        // Deixe vazio ou implemente apenas se souber exatamente os tipos antigos.
    }
};
