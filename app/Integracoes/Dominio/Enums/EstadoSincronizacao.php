<?php

namespace App\Integracoes\Dominio\Enums;

/** Estados canônicos de sincronização (docs/009 §21.6, RN-093). */
enum EstadoSincronizacao: string
{
    case NaoEnviado = 'nao_enviado';
    case Aguardando = 'aguardando';
    case Processando = 'processando';
    case Enviado = 'enviado';
    case Sincronizado = 'sincronizado';
    case AtualizacaoPendente = 'atualizacao_pendente';
    case Falha = 'falha';
    case Removido = 'removido';
    case IntervencaoNecessaria = 'intervencao_necessaria';
}
