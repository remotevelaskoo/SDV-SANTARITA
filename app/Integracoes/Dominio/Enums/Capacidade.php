<?php

namespace App\Integracoes\Dominio\Enums;

/**
 * Capacidades que um adaptador pode declarar (ADR-007 §6, docs/008 §24.4).
 * Operação cuja capacidade não foi declarada nem verificada não é oferecida
 * nem executada (RN-091).
 */
enum Capacidade: string
{
    case TestarConexao = 'testar_conexao';
    // Inventário: modelo, série e firmware informados pelo terminal.
    case ConsultarInformacoes = 'consultar_informacoes';
    case ConsultarCapacidades = 'consultar_capacidades';
    // Cadastro de pessoas no terminal (fora desta entrega).
    case GerenciarPessoas = 'gerenciar_pessoas';
    case SincronizarCredencial = 'sincronizar_credencial';
    case RevogarCredencial = 'revogar_credencial';
    case ConsultarSincronizacao = 'consultar_sincronizacao';
    case ColetarEventos = 'coletar_eventos';
    case ReceberEventos = 'receber_eventos';
    case AberturaRemota = 'abertura_remota';
    case ConsultarResultadoComando = 'consultar_resultado_comando';
    case CredencialFacial = 'credencial_facial';
    case IdempotenciaNativa = 'idempotencia_nativa';
    // Imagem estática da câmera para conferência visual; nunca vira credencial biométrica.
    case CapturarImagem = 'capturar_imagem';
}
