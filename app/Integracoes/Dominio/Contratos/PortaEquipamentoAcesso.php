<?php

namespace App\Integracoes\Dominio\Contratos;

use App\Integracoes\Dominio\Dados\CapacidadesDeclaradas;
use App\Integracoes\Dominio\Dados\ComandoAbertura;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Dados\ResultadoColetaEventos;
use App\Integracoes\Dominio\Dados\ResultadoOperacao;

/**
 * Porta estável entre o núcleo do SDV e qualquer terminal ou controladora
 * de ponto de acesso (ADR-007 §3 e §5, ADR-016 §7).
 *
 * Regras do contrato, verificadas pelos testes de contrato:
 * - operação sem capacidade comprovada devolve `capacidade_ausente`, nunca sucesso simulado;
 * - timeout devolve `confirmacao_desconhecida`, nunca sucesso nem falha confirmada;
 * - equipamento inalcançável devolve `indisponivel`;
 * - nenhuma exceção de SDK ou cliente HTTP atravessa a porta;
 * - nenhum dado devolvido contém segredo, imagem ou template.
 */
interface PortaEquipamentoAcesso
{
    /** Código estável do adaptador, gravado em `equipamentos.adaptador`. */
    public function codigo(): string;

    public function versaoContrato(): string;

    public function testarConexao(ContextoEquipamento $equipamento): ResultadoOperacao;

    public function consultarCapacidades(ContextoEquipamento $equipamento): CapacidadesDeclaradas;

    public function sincronizarCredencial(ContextoEquipamento $equipamento, CredencialParaSincronizar $credencial): ResultadoOperacao;

    public function revogarCredencial(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao;

    public function consultarSincronizacao(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao;

    public function coletarEventos(ContextoEquipamento $equipamento, ?string $cursor): ResultadoColetaEventos;

    public function solicitarAbertura(ContextoEquipamento $equipamento, ComandoAbertura $comando): ResultadoOperacao;

    /** Consulta o resultado de um comando anterior quando o equipamento oferece essa prova. */
    public function consultarResultadoComando(ContextoEquipamento $equipamento, string $comandoId): ResultadoOperacao;
}
