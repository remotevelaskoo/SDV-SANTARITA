<?php

namespace App\Documentos\Aplicacao;

use App\Documentos\Dominio\PortaOcrDocumento;
use App\Documentos\Dominio\ResultadoOcr;
use App\Documentos\Infra\AvaliadorQualidadeImagem;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\PessoaDocumento;
use App\Models\User;
use App\Services\AuditService;

/**
 * Cadastro rápido assistido por câmera (SDV-OPE-018 §5, §7 e §8).
 *
 * Avalia a qualidade da captura antes do OCR; com qualidade ruim, não
 * preenche nada e orienta nova captura. O OCR só SUGERE: o resultado nunca
 * aprova cadastro, cria vínculo ou autorização, libera acesso ou vira
 * biometria. Imagens ficam só em memória durante a análise; nada de imagem,
 * CPF ou conteúdo documental vai para log ou auditoria.
 */
class CadastroAssistidoDocumento
{
    public const PERMISSAO = 'documentos.capturar';

    public function __construct(
        private AvaliadorQualidadeImagem $qualidade,
        private PortaOcrDocumento $ocr,
        private AuditService $audit,
    ) {}

    /**
     * @return array{status: 'repetir'|'nao_reconhecido'|'sugestao', problemas: array<string, array<string, string>>,
     *     campos: array<string, array{valor: ?string, confianca: float, conferir: bool}>, conferir: list<string>,
     *     duplicidade: bool, mensagem: ?string}
     */
    public function analisar(User $operador, string $frente, ?string $verso = null, string $origem = 'camera', string $tipoDocumento = 'cnh'): array
    {
        if (! $operador->hasPermission(self::PERMISSAO)) {
            throw new RegraIntegracaoViolada('sem_permissao', 'Você não tem permissão para capturar documentos.');
        }
        if (! in_array($tipoDocumento, PortaOcrDocumento::TIPOS_DOCUMENTO, true)) {
            throw new RegraIntegracaoViolada('tipo_documento_invalido', 'Escolha CNH ou RG/CIN.');
        }

        $problemas = [];
        foreach (['frente' => $frente, 'verso' => $verso] as $lado => $conteudo) {
            if ($conteudo === null) {
                continue;
            }
            $this->validarArquivo($conteudo);
            $avaliacao = $this->qualidade->avaliar($conteudo);
            if (! $avaliacao['aprovada']) {
                $problemas[$lado] = $avaliacao['problemas'];
            }
        }

        if ($problemas !== []) {
            $this->auditar('repetir', $origem, $verso !== null, array_map('array_keys', $problemas), null, []);

            return ['status' => 'repetir', 'problemas' => $problemas, 'campos' => [], 'conferir' => [], 'duplicidade' => false, 'mensagem' => 'Capture novamente: a qualidade da imagem não permite a leitura.'];
        }

        $resultado = $this->ocr->extrair($frente, $verso, $tipoDocumento);
        if (! $resultado->reconhecido) {
            $this->auditar('nao_reconhecido', $origem, $verso !== null, [], $resultado, []);

            return ['status' => 'nao_reconhecido', 'problemas' => [], 'campos' => [], 'conferir' => [], 'duplicidade' => false, 'mensagem' => $resultado->mensagem];
        }

        $minima = (float) config('documentos.ocr.confianca_minima');
        $conferir = $resultado->camposParaConferencia($minima);
        $campos = [];
        foreach (ResultadoOcr::CAMPOS as $campo) {
            $campos[$campo] = [
                'valor' => $resultado->valor($campo),
                'confianca' => $resultado->confianca($campo),
                'conferir' => in_array($campo, $conferir, true),
            ];
        }

        $cpf = preg_replace('/\D/', '', (string) $resultado->valor('cpf')) ?? '';
        $duplicidade = $cpf !== '' && PessoaDocumento::query()->where('tipo', 'cpf')->where('valor_normalizado', $cpf)->exists();

        $this->auditar('sugestao', $origem, $verso !== null, [], $resultado, $conferir, $duplicidade);

        return ['status' => 'sugestao', 'problemas' => [], 'campos' => $campos, 'conferir' => $conferir, 'duplicidade' => $duplicidade, 'mensagem' => null];
    }

    private function validarArquivo(string $conteudo): void
    {
        $maximo = (int) config('documentos.captura.tamanho_maximo_kb') * 1024;
        $info = @getimagesizefromstring($conteudo);
        if ($conteudo === '' || strlen($conteudo) > $maximo || $info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new RegraIntegracaoViolada('arquivo_invalido', 'Envie uma imagem JPEG ou PNG de até '.intdiv($maximo, 1024 * 1024).' MB.');
        }
    }

    /**
     * Auditoria sem conteúdo sensível: só resultado, origem, códigos de
     * problema e NOMES dos campos que exigem conferência.
     *
     * @param  array<string, list<string>>|array{}  $problemas
     * @param  list<string>  $conferir
     */
    private function auditar(string $status, string $origem, bool $comVerso, array $problemas, ?ResultadoOcr $resultado, array $conferir, bool $duplicidade = false): void
    {
        $this->audit->record(
            action: 'documento_capturado_analisado',
            module: 'cadastro',
            entityType: 'captura_documento',
            result: $status === 'sugestao' ? 'sucesso' : 'falha',
            reasonCode: $status,
            classification: 'restrita',
            metadata: [
                'origem' => $origem === 'arquivo' ? 'arquivo' : 'camera',
                'com_verso' => $comVerso,
                'problemas_qualidade' => $problemas,
                'campos_lidos' => $resultado ? array_keys(array_filter($resultado->campos, fn ($c) => ($c['valor'] ?? null) !== null)) : [],
                'campos_para_conferencia' => $conferir,
                'possivel_duplicidade' => $duplicidade,
                'retencao' => config('documentos.captura.retencao'),
            ],
        );
    }
}
