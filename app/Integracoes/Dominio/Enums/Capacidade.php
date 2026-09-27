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
    case ConsultarCapacidades = 'consultar_capacidades';
    case SincronizarCredencial = 'sincronizar_credencial';
    case RevogarCredencial = 'revogar_credencial';
    case ConsultarSincronizacao = 'consultar_sincronizacao';
    case ColetarEventos = 'coletar_eventos';
    case ReceberEventos = 'receber_eventos';
    case AberturaRemota = 'abertura_remota';
    case ConsultarResultadoComando = 'consultar_resultado_comando';
    case CredencialFacial = 'credencial_facial';
    case IdempotenciaNativa = 'idempotencia_nativa';
}
