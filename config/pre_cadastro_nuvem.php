<?php

return [
    'enabled' => (bool) env('SDV_PRE_CADASTRO_NUVEM_ENABLED', false),
    'url' => env('SDV_PRE_CADASTRO_NUVEM_URL'),
    'token' => env('SDV_PRE_CADASTRO_NUVEM_TOKEN'),
    'implantacao_id' => env('SDV_PRE_CADASTRO_NUVEM_IMPLANTACAO_ID'),
];
