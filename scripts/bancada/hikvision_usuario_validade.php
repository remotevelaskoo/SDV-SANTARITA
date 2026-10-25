<?php

/**
 * Bancada AJ-006 / F7 — usuário com período de validade na facial.
 *
 * Roteiro manual, executado só por ação explícita do responsável na bancada.
 * Não faz parte do sistema nem dos testes automatizados. Usa um usuário de
 * teste FICTÍCIO, sem foto e sem template, e nunca toca nos usuários que já
 * existem no terminal: só lê, grava e remove o employeeNo de teste.
 *
 * Passos (cada um mostra o pedido e a resposta):
 *   1. relogio      GET  /ISAPI/System/time                    fuso e hora do terminal
 *   2. capacidades  GET  /ISAPI/AccessControl/UserInfo/capabilities?format=json
 *   3. gravar       POST /ISAPI/AccessControl/UserInfo/Record?format=json   (Valid begin/end)
 *   4. consultar    POST /ISAPI/AccessControl/UserInfo/Search?format=json
 *   5. remover      PUT  /ISAPI/AccessControl/UserInfo/Delete?format=json
 *
 * Os caminhos vêm da documentação ISAPI de controle de acesso e estão sendo
 * VALIDADOS por este roteiro: só entram no adaptador depois do retorno real.
 *
 * Uso (senha só por variável de ambiente, nunca no comando nem no Git):
 *   export SDV_BANCADA_SENHA='...'
 *   php scripts/bancada/hikvision_usuario_validade.php --host=IP --pin=CHAVE [--usuario=admin]
 *       [--minutos=1440] [--passos=relogio,capacidades,gravar,consultar,remover] [--manter]
 *
 *   --pin       pin SHA-256 da chave pública do certificado (o mesmo da tela de Equipamentos).
 *               Sem pin, use --http para porta 80, ou --aceitar-certificado-da-bancada.
 *   --minutos   duração da validade a partir de agora (padrão 1440 = 24h; use 5 para ver vencer).
 *   --manter    não remove o usuário no fim, para conferir na tela web da facial.
 *   --remover   só remove o usuário de teste (limpeza).
 *
 * O relatório vai para scripts/bancada/saida/ (ignorado pelo Git) sem senha.
 */
const EMPLOYEE_NO = 'SDVTESTE0001';

// Início e fim saem na hora local da portaria, mesmo rodando num container em UTC.
date_default_timezone_set(getenv('SDV_FUSO_OPERACIONAL') ?: 'America/Sao_Paulo');

const NOME = 'TESTE SDV BANCADA';

$opcoes = getopt('', ['host:', 'usuario:', 'pin:', 'http', 'aceitar-certificado-da-bancada', 'minutos:', 'passos:', 'manter', 'remover', 'porta-acesso:']);
$host = $opcoes['host'] ?? null;
$senha = getenv('SDV_BANCADA_SENHA') ?: null;
if ($host === null || $senha === null) {
    fwrite(STDERR, "Informe --host=IP e defina SDV_BANCADA_SENHA no ambiente.\n");
    exit(2);
}
if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    fwrite(STDERR, "Use o IPv4 privado da facial na rede da bancada.\n");
    exit(2);
}

$usuario = $opcoes['usuario'] ?? 'admin';
$http = isset($opcoes['http']);
$base = ($http ? 'http' : 'https').'://'.$host;
$minutos = max(1, min(1440, (int) ($opcoes['minutos'] ?? 1440)));
$porta = (int) ($opcoes['porta-acesso'] ?? 1);
$passos = isset($opcoes['remover'])
    ? ['remover']
    : explode(',', $opcoes['passos'] ?? 'relogio,capacidades,gravar,consultar'.(isset($opcoes['manter']) ? '' : ',remover'));

$relatorio = ['executado_em' => date(DATE_ATOM), 'fuso_maquina' => date_default_timezone_get(), 'employee_no' => EMPLOYEE_NO, 'passos' => []];

function chamar(string $metodo, string $caminho, ?array $corpo = null): array
{
    global $base, $usuario, $senha, $opcoes, $http;

    $curl = curl_init($base.$caminho);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => $usuario.':'.$senha,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if (! $http) {
        if (isset($opcoes['pin'])) {
            // Mesmo modelo do adaptador: sem cadeia de CA, mas aborta se a chave mudar.
            curl_setopt($curl, CURLOPT_PINNEDPUBLICKEY, 'sha256//'.$opcoes['pin']);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        } elseif (isset($opcoes['aceitar-certificado-da-bancada'])) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }
    if ($corpo !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $resposta = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $erro = $resposta === false ? 'curl '.curl_errno($curl).': '.curl_error($curl) : null;
    curl_close($curl);

    return ['metodo' => $metodo, 'caminho' => $caminho, 'enviado' => $corpo, 'status' => $status, 'erro' => $erro, 'resposta' => $resposta === false ? null : mb_substr((string) $resposta, 0, 20000)];
}

function registrar(string $passo, array $resultado): void
{
    global $relatorio;
    $relatorio['passos'][$passo] = $resultado;
    echo "\n=== {$passo}: {$resultado['metodo']} {$resultado['caminho']} → HTTP {$resultado['status']}".($resultado['erro'] ? " ({$resultado['erro']})" : '')."\n";
    if ($resultado['enviado'] !== null) {
        echo 'Enviado: '.json_encode($resultado['enviado'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
    echo 'Resposta: '.($resultado['resposta'] ?? '(nenhuma)')."\n";
}

$inicio = new DateTimeImmutable('now');
$fim = $inicio->modify("+{$minutos} minutes");

foreach ($passos as $passo) {
    switch (trim($passo)) {
        case 'relogio':
            registrar('relogio', chamar('GET', '/ISAPI/System/time'));
            echo 'Hora desta máquina: '.$inicio->format('Y-m-d H:i:s P')."\n";
            break;

        case 'capacidades':
            registrar('capacidades', chamar('GET', '/ISAPI/AccessControl/UserInfo/capabilities?format=json'));
            break;

        case 'gravar':
            // Hora local do terminal, sem fuso no texto (timeType local).
            registrar('gravar', chamar('POST', '/ISAPI/AccessControl/UserInfo/Record?format=json', ['UserInfo' => [
                'employeeNo' => EMPLOYEE_NO,
                'name' => NOME,
                'userType' => 'normal',
                'Valid' => [
                    'enable' => true,
                    'beginTime' => $inicio->format('Y-m-d\TH:i:s'),
                    'endTime' => $fim->format('Y-m-d\TH:i:s'),
                    'timeType' => 'local',
                ],
                'doorRight' => (string) $porta,
                'RightPlan' => [['doorNo' => $porta, 'planTemplateNo' => '1']],
            ]]));
            break;

        case 'consultar':
            registrar('consultar', chamar('POST', '/ISAPI/AccessControl/UserInfo/Search?format=json', ['UserInfoSearchCond' => [
                'searchID' => 'sdv-bancada-'.bin2hex(random_bytes(4)),
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'EmployeeNoList' => [['employeeNo' => EMPLOYEE_NO]],
            ]]));
            break;

        case 'remover':
            registrar('remover', chamar('PUT', '/ISAPI/AccessControl/UserInfo/Delete?format=json', ['UserInfoDelCond' => [
                'EmployeeNoList' => [['employeeNo' => EMPLOYEE_NO]],
            ]]));
            break;

        default:
            fwrite(STDERR, "Passo desconhecido: {$passo}\n");
    }
}

$pasta = __DIR__.'/saida';
if (! is_dir($pasta)) {
    mkdir($pasta, 0700, true);
}
$arquivo = $pasta.'/usuario_validade_'.date('Ymd_His').'.json';
file_put_contents($arquivo, json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\nRelatório (sem senha): {$arquivo}\n";
