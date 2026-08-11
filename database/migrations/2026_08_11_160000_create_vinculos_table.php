<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escopo desta fatia: vínculo pessoa+imóvel apenas. Vínculo com empresa
 * (docs/010 seção 9) fica fora até o grupo Empresas existir como tabelas
 * reais (hoje é só protótipo em array no P13).
 *
 * `tipo` (natureza) e `papel` ficam como texto simples — os catálogos
 * definitivos de vínculo/papel/responsabilidade seguem pendentes
 * (PEN-BDD-003), mesma decisão provisória já usada em pre_registrations
 * .access_type e pessoa_documentos.valor_normalizado.
 *
 * `vinculo_periodos` (histórico de renovação) fica fora desta fatia — a
 * vigência atual é guardada inline (started_at/ended_at), igual ao padrão
 * já usado em enderecos_imoveis e pessoa_documentos. Prevenção de
 * sobreposição por EXCLUDE constraint do PostgreSQL não é aplicada aqui
 * (SQLite não suporta; docs/010 permite ficar só na validação de
 * aplicação até essa validação ser construída).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignUuid('imovel_id')->constrained('imoveis')->restrictOnDelete();
            $table->string('tipo');
            $table->string('papel')->nullable();
            $table->string('status')->default('ativo');
            $table->string('origem')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pessoa_id', 'status']);
            $table->index(['imovel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculos');
    }
};
