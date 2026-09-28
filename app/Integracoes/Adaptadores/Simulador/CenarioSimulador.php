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
    // Homologação da facial em bancada.
    case CredencialInvalida = 'credencial_invalida';
    case CertificadoInvalido = 'certificado_invalido';
    case CapturaIndisponivel = 'captura_indisponivel';
    // O terminal aceitou o comando, sem prova de execução física.
    case Aceito = 'aceito';
}
