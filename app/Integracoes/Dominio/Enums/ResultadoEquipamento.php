<?php

namespace App\Integracoes\Dominio\Enums;

/**
 * Resultado conhecido do efeito no equipamento (ADR-007 §8, RN-078, RN-080).
 * "Aceito" não é abertura física; "desconhecido" nunca vira sucesso sem
 * reconciliação com prova.
 */
enum ResultadoEquipamento: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Aceito = 'aceito';
    case Recusado = 'recusado';
    case Confirmado = 'confirmado';
    case FalhaTecnica = 'falha_tecnica';
    case ConfirmacaoDesconhecida = 'confirmacao_desconhecida';
    case Expirado = 'expirado';
    case IntervencaoNecessaria = 'intervencao_necessaria';
    case CapacidadeAusente = 'capacidade_ausente';
    case Indisponivel = 'indisponivel';

    public function ehSucessoComprovado(): bool
    {
        return $this === self::Confirmado;
    }

    /** Falha em que o equipamento comprovadamente não recebeu a operação. */
    public function naoAlcancouEquipamento(): bool
    {
        return $this === self::Indisponivel;
    }
}
