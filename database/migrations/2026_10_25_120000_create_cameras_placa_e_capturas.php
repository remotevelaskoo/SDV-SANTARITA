<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Câmera IP de placas (AJ-006, fatia 5).
 *
 * - a câmera é um equipamento com `tipo = camera_placas` e
 *   `finalidade = placas`; o caminho do snapshot fica no backend, como o IP,
 *   a porta e a referência da credencial;
 * - o vínculo câmera ↔ ponto tem tabela própria: um ponto pode ter mais de
 *   uma câmera e o vínculo exclusivo terminal ↔ ponto não é afetado;
 * - cada captura vira evidência privada (`arquivos`) e, quando entra numa
 *   decisão, fica ligada ao histórico de acesso e não muda mais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipamentos', function (Blueprint $table) {
            $table->string('finalidade', 30)->nullable()->after('tipo');
            $table->string('caminho_snapshot', 255)->nullable()->after('protocolo');
        });

        Schema::create('camera_ponto_vinculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignUuid('ponto_acesso_id')->constrained('pontos_acesso')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('motivo_encerramento')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['implantacao_id', 'ponto_acesso_id']);
        });
        DB::statement('CREATE UNIQUE INDEX camera_ponto_vinculos_camera_vigente_unique ON camera_ponto_vinculos (equipamento_id) WHERE ended_at IS NULL');

        Schema::create('capturas_placa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignUuid('ponto_acesso_id')->nullable()->constrained('pontos_acesso')->nullOnDelete();
            $table->foreignUuid('posto_id')->nullable()->constrained('postos')->nullOnDelete();
            $table->foreignUuid('arquivo_id')->unique()->constrained('arquivos')->restrictOnDelete();
            $table->foreignId('operador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('capturada_em');
            // camera = imagem obtida do equipamento; simulador = imagem sintética de teste.
            $table->string('origem', 20);
            // Etapa de leitura (LPR) separada da captura; vazia até existir leitor.
            $table->string('placa_lida', 10)->nullable();
            $table->decimal('confianca_leitura', 5, 4)->nullable();
            $table->string('leitor', 60)->nullable();
            $table->foreignUuid('historico_acesso_id')->nullable()->unique()->constrained('historico_acessos')->restrictOnDelete();
            $table->dateTime('vinculada_em')->nullable();
            $table->timestamps();

            $table->index(['implantacao_id', 'capturada_em']);
        });

        Schema::table('historico_acessos', function (Blueprint $table) {
            $table->string('placa_informada', 10)->nullable();
            // manual = digitada pelo operador; conferida = confirmada sobre a imagem capturada.
            $table->string('placa_origem', 20)->nullable();
            $table->foreignUuid('captura_placa_id')->nullable()->constrained('capturas_placa')->nullOnDelete();

            $table->index(['implantacao_id', 'placa_informada']);
        });
    }

    public function down(): void
    {
        Schema::table('historico_acessos', function (Blueprint $table) {
            $table->dropIndex(['implantacao_id', 'placa_informada']);
            $table->dropConstrainedForeignId('captura_placa_id');
            $table->dropColumn(['placa_informada', 'placa_origem']);
        });
        Schema::dropIfExists('capturas_placa');
        Schema::dropIfExists('camera_ponto_vinculos');
        Schema::table('equipamentos', function (Blueprint $table) {
            $table->dropColumn(['finalidade', 'caminho_snapshot']);
        });
    }
};
