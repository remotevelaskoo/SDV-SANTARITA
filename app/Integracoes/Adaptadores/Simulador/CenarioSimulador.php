<?php

namespace App\Integracoes\Adaptadores\Simulador;

/** Cenários exigidos pelo ADR-007 §14 para o simulador contratual. */
enum CenarioSimulador: string
{
    case Sucesso = 'sucesso';
    case Recusa = 'recusa';
    case Timeout = 'timeout';
    case CallbackTardio = 'callback_tardio';
    case Duplicidade = 'duplicidade';
    case Indisponivel = 'indisponivel';
    case PayloadInvalido = 'payload_invalido';
    case CapacidadeAusente = 'capacidade_ausente';
    case ConfirmacaoDesconhecida = 'confirmacao_desconhecida';
    case Excecao = 'excecao';
}
