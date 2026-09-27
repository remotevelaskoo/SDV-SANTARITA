<?php

namespace App\Integracoes\Dominio\Enums;

/** Operações que passam pela outbox e a capacidade exigida por cada uma. */
enum OperacaoIntegracao: string
{
    case TestarConexao = 'testar_conexao';
    case ConsultarCapacidades = 'consultar_capacidades';
    case SincronizarCredencial = 'sincronizar_credencial';
    case RevogarCredencial = 'revogar_credencial';
    case ConsultarSincronizacao = 'consultar_sincronizacao';
    case ColetarEventos = 'coletar_eventos';
    case AberturaRemota = 'abertura_remota';
    case ConsultarResultadoComando = 'consultar_resultado_comando';

    public function capacidadeExigida(): ?Capacidade
    {
        return match ($this) {
            // Sem estas duas não há como sequer descobrir o que o equipamento suporta.
            self::TestarConexao, self::ConsultarCapacidades => null,
            self::SincronizarCredencial => Capacidade::SincronizarCredencial,
            self::RevogarCredencial => Capacidade::RevogarCredencial,
            self::ConsultarSincronizacao => Capacidade::ConsultarSincronizacao,
            self::ColetarEventos => Capacidade::ColetarEventos,
            self::AberturaRemota => Capacidade::AberturaRemota,
            self::ConsultarResultadoComando => Capacidade::ConsultarResultadoComando,
        };
    }

    /** Fila lógica conforme ADR-005 §13. */
    public function fila(): string
    {
        return $this === self::AberturaRemota ? 'access-critical' : 'integrations';
    }

    /**
     * Repetir é seguro somente quando o efeito é idempotente no equipamento
     * ou é leitura. Abertura nunca é repetida automaticamente (ADR-007 §9).
     */
    public function permiteRetentativaAutomatica(): bool
    {
        return $this !== self::AberturaRemota;
    }
}
