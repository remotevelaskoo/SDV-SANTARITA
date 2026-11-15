<?php

return [
    'enabled' => (bool) env('SDV_PRE_CADASTRO_NUVEM_ENABLED', false),
    'url' => env('SDV_PRE_CADASTRO_NUVEM_URL'),
    'token' => env('SDV_PRE_CADASTRO_NUVEM_TOKEN'),
    'implantacao_id' => env('SDV_PRE_CADASTRO_NUVEM_IMPLANTACAO_ID'),
    // Link divulgado a todos (https://portal/#convite=CÓDIGO). Fora do Git.
    'link_publico' => env('SDV_PRE_CADASTRO_LINK_PUBLICO'),
    // Painel de gestão fora do condomínio (PO 15/11/2026): mesma URL e credencial do nó.
    'gestao_enabled' => (bool) env('SDV_GESTAO_NUVEM_ENABLED', false),
];
