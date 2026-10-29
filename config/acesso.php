<?php

/*
 * Autorizações de acesso e envio aos terminais (AJ-006, fatias 6 e 7).
 */
return [
    // Fuso em que a portaria opera. O servidor precisa estar no mesmo fuso
    // para calcular início e fim das autorizações de turista.
    'fuso_operacional' => env('SDV_FUSO_OPERACIONAL', 'America/Sao_Paulo'),

    'turista' => [
        // Teto da credencial do turista a partir do início aprovado. Valores
        // acima de 24 são ignorados: a regra do PO é no máximo 24 horas.
        'validade_horas' => (int) env('SDV_TURISTA_VALIDADE_HORAS', 24),
    ],

    'revogacao' => [
        // Revogação que esgotou as tentativas por falta de comunicação é
        // pedida de novo depois deste intervalo, até o terminal confirmar.
        'repetir_apos_minutos' => (int) env('SDV_REVOGACAO_REPETIR_MINUTOS', 15),
    ],
];
