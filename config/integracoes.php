<?php

use App\Integracoes\Adaptadores\Hikvision\HikvisionIsapiAdaptador;
use App\Integracoes\Adaptadores\Simulador\SimuladorEquipamento;

/*
|--------------------------------------------------------------------------
| Integrações com equipamentos (ADR-007, ADR-016)
|--------------------------------------------------------------------------
|
| Os padrões abaixo são deliberadamente restritivos. Nenhuma chave aqui
| contém segredo: credenciais técnicas ficam fora do banco e do código e
| são referenciadas por `equipamento_credenciais.referencia_segredo`.
|
*/

return [

    // Código do adaptador => classe que implementa PortaEquipamentoAcesso.
    'adaptadores' => [
        'hikvision-isapi' => HikvisionIsapiAdaptador::class,
        'simulador' => SimuladorEquipamento::class,
    ],

    // O simulador só é aceito fora de produção (ADR-007 §14: não substitui hardware real).
    'simulador_permitido' => env('SDV_INTEGRACAO_SIMULADOR_PERMITIDO', env('APP_ENV') !== 'production'),

    // Abertura remota fica desligada até a homologação em bancada (ADR-016 §11, passos 8 e 9).
    'abertura_remota_habilitada' => (bool) env('SDV_INTEGRACAO_ABERTURA_REMOTA', false),

    // Validade máxima de um comando de abertura antes de expirar sem envio.
    'abertura_validade_segundos' => 15,

    // Prefixos aceitos para referência de segredo (ADR-009 §6). `vault:` fica
    // reservado até a escolha do cofre de produção.
    'segredos' => [
        'prefixos' => ['env'],
    ],

    'outbox' => [
        'lote' => 20,
        'lease_segundos' => 60,
        'max_tentativas' => 5,
        'backoff_base_segundos' => 10,
        'backoff_maximo_segundos' => 600,
    ],

    // Divergência de relógio acima deste valor é registrada no evento recebido.
    'tolerancia_relogio_segundos' => 120,

    'hikvision' => [
        // Perfis de firmware homologados: firmware => capacidades comprovadas em bancada.
        // Vazio até existir documentação ISAPI compatível com o firmware instalado
        // (PEN-ADR-016-001 e PEN-ADR-016-002). Enquanto vazio, o adaptador
        // declara todas as capacidades como ausentes.
        'perfis_homologados' => [],
    ],

];
