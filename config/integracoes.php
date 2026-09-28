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

    // Teste do relé em bancada, com o terminal "Em homologação" e sem cancela
    // ou catraca ligada (ADR-016 §11, passo 8). Independe da abertura remota
    // operacional acima, que continua desligada.
    'teste_rele_habilitado' => (bool) env('SDV_INTEGRACAO_TESTE_RELE', false),

    // Validade máxima de um comando de abertura antes de expirar sem envio.
    'abertura_validade_segundos' => 15,

    // Prefixos aceitos para referência de segredo (ADR-009 §6). `vault:` fica
    // reservado até a escolha do cofre de produção.
    'segredos' => [
        'prefixos' => ['env'],
        // Senha digitada na tela e cifrada com APP_KEY. Permitida fora de
        // produção; produção exige cofre (ADR-009 §3, PEN-ADR-009-001).
        'cifrado_permitido' => (bool) env('SDV_INTEGRACAO_SEGREDO_CIFRADO', env('APP_ENV') !== 'production'),
    ],

    // Imagem estática da câmera: só em memória e em cache cifrado de curta
    // duração; não há arquivo nem histórico de imagens (sem política aprovada).
    'captura' => [
        'tamanho_maximo_bytes' => 3 * 1024 * 1024,
        'retencao_segundos' => 300,
    ],

    // Valores sugeridos no formulário de cadastro da bancada de homologação.
    'homologacao_padrao' => [
        'nome' => 'Facial Homologação 01',
        'fabricante' => 'Hikvision',
        'modelo' => 'DS-K1T673DX-BR',
        'endereco_rede' => env('SDV_HOMOLOGACAO_FACIAL_HOST', ''),
        'esquema' => 'https',
        'protocolo' => 'isapi',
        'adaptador' => 'hikvision-isapi',
        'usuario_tecnico' => 'admin',
        'ponto_codigo' => 'BANCADA-01',
        'ponto_nome' => 'Bancada de testes',
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
        // Matriz de homologação: firmware informado pelo terminal em
        // deviceInfo ("versão build") => estado de cada capacidade, com a
        // evidência registrada em docs/016. Só `homologada` e
        // `em_homologacao` executam; `em_homologacao` somente com o terminal
        // em homologação (bancada). Capacidade fora da lista é classificada
        // pelo adaptador (não implementada, não suportada ou detectada).
        // Firmware fora desta lista não executa nada.
        'perfis_homologados' => [
            'V3.18.0 build 250115' => [
                'testar_conexao' => 'homologada',
                'consultar_informacoes' => 'homologada',
                'consultar_capacidades' => 'homologada',
                'capturar_imagem' => 'homologada',
                // Relé acionado em bancada em 27/09/2026 (docs/016 §5). A abertura
                // operacional continua desligada (SDV_INTEGRACAO_ABERTURA_REMOTA).
                'abertura_remota' => 'homologada',
                // Biometria depende do ADR-013.
                'credencial_facial' => 'bloqueada',
            ],
        ],

        // Canal de vídeo da captura. Nulo = descobrir em /Streaming/channels.
        'canal_imagem' => null,
        // Porta (relé) acionada pelo comando remoto.
        'porta_rele' => 1,
    ],

];
