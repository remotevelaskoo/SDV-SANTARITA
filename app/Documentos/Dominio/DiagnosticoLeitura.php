<?php

namespace App\Documentos\Dominio;

/**
 * Por que a leitura automática do documento falhou ou ficou incompleta
 * (AJ-006, fatia 4), separando as etapas: captura, qualidade, comunicação,
 * serviço, OCR, interpretação e validação.
 *
 * Só carrega códigos e orientações objetivas, nunca texto lido, valores ou
 * imagem, e por isso pode ir para log. A leitura continua sendo sugestão:
 * o diagnóstico orienta nova captura ou preenchimento manual e não aprova
 * nem autoriza nada.
 */
final readonly class DiagnosticoLeitura
{
    public const CATEGORIAS = [
        'ok' => 'Leitura concluída',
        'parcial' => 'Leitura parcial',
        'captura' => 'Arquivo não aceito',
        'qualidade' => 'Qualidade da imagem',
        'comunicacao' => 'Comunicação com o leitor',
        'servico' => 'Leitor indisponível',
        'ocr' => 'Nenhum texto reconhecido',
        'interpretacao' => 'Campos não localizados',
        'validacao' => 'Dados lidos com problema',
    ];

    public const MENSAGENS = [
        // Captura e qualidade (medidas na imagem antes do OCR).
        'imagem_invalida' => 'O arquivo não é uma imagem válida. Envie uma foto em JPG, PNG ou WEBP.',
        'arquivo_grande' => 'A imagem é grande demais. Envie uma foto de até 8 MB.',
        'resolucao_baixa' => 'Resolução baixa: aproxime o documento ou use a câmera traseira.',
        'escura' => 'Imagem escura: procure um local mais iluminado.',
        'clara_demais' => 'Imagem clara demais: evite luz direta sobre o documento.',
        'reflexo' => 'Há reflexo sobre o documento: incline um pouco o documento ou mude de posição em relação à luz.',
        'desfocada' => 'Imagem desfocada: apoie o documento numa superfície e espere o foco antes de fotografar.',
        'baixo_contraste' => 'Pouco contraste: fotografe sobre um fundo escuro e liso.',
        'possivelmente_cortado' => 'O documento parece cortado: deixe as quatro bordas visíveis na foto.',
        // OCR e interpretação.
        'sem_texto' => 'Nenhum texto foi reconhecido. Fotografe o documento inteiro, de frente e fora da capa plástica.',
        'campos_nao_localizados' => 'O texto foi lido, mas os campos não foram localizados. Confira se a foto é do lado que tem os dados.',
        'tipo_divergente' => 'A imagem parece ser de outro tipo de documento. Confira se escolheu CNH ou RG/CIN corretamente.',
        'verso_recomendado' => 'No RG, parte dos dados fica no verso. Envie também a foto do verso.',
        // Validação dos dados lidos.
        'cpf_digito_invalido' => 'O CPF lido não é válido e não foi sugerido. Digite o CPF conferindo o documento.',
        'confianca_baixa' => 'Alguns campos foram lidos com pouca confiança. Confira cada um com atenção.',
        // Comunicação e serviço.
        'sem_resposta' => 'O leitor automático não respondeu a tempo. Tente de novo em instantes ou preencha manualmente.',
        'desligado' => 'A leitura automática não está ativa neste ambiente. Preencha os dados manualmente.',
        'nao_configurado' => 'A leitura automática não está disponível agora. Preencha os dados manualmente.',
        'nao_autorizado' => 'A leitura automática não está disponível agora. Preencha os dados manualmente.',
        'erro_interno' => 'O leitor automático teve um problema. Tente de novo ou preencha manualmente.',
        'resposta_invalida' => 'O leitor automático devolveu uma resposta inesperada. Tente de novo ou preencha manualmente.',
        'tentativas_excedidas' => 'Foram feitas muitas tentativas de leitura. Aguarde um minuto ou preencha manualmente.',
    ];

    /** Etapa do serviço Python => categoria do SDV. */
    private const ETAPAS = [
        'ok' => 'ok',
        'partial' => 'parcial',
        'capture' => 'captura',
        'quality' => 'qualidade',
        'ocr' => 'ocr',
        'interpretation' => 'interpretacao',
    ];

    /** Códigos que, sozinhos, transformam uma leitura completa em problema de validação. */
    private const VALIDACAO = ['cpf_digito_invalido', 'confianca_baixa'];

    /**
     * @param  list<string>  $codigos
     * @param  list<string>  $avisosQualidade
     */
    public function __construct(
        public string $categoria,
        public array $codigos = [],
        public array $avisosQualidade = [],
        public bool $recapturar = false,
        public bool $orientacaoCorrigida = false,
    ) {}

    /** Diagnóstico de uma resposta concluída do leitor. */
    public static function daResposta(array $resposta): self
    {
        $diagnostico = is_array($resposta['diagnostics'] ?? null) ? $resposta['diagnostics'] : [];
        $codigos = self::codigosConhecidos($diagnostico['issues'] ?? []);
        $qualidade = self::codigosConhecidos($diagnostico['quality_issues'] ?? []);
        $categoria = self::ETAPAS[$diagnostico['stage'] ?? ''] ?? null;

        if ($categoria === null) {
            // Leitor anterior ao AJ-006, sem diagnóstico: deduz pelos campos.
            $encontrados = array_filter((array) ($resposta['fields'] ?? []), fn ($campo) => is_array($campo) && ($campo['value'] ?? '') !== '' && ($campo['value'] ?? null) !== null);
            $categoria = $encontrados === [] ? 'interpretacao' : 'ok';
        }

        if ($categoria === 'ok' && array_intersect($codigos, self::VALIDACAO) !== []) {
            $categoria = 'validacao';
        }

        return new self(
            categoria: $categoria,
            codigos: $codigos,
            avisosQualidade: $qualidade,
            recapturar: (bool) ($diagnostico['recapture_recommended'] ?? false),
            orientacaoCorrigida: ! empty($diagnostico['rotation_applied']) || ! empty($diagnostico['exif_rotated']),
        );
    }

    public static function daFalha(FalhaLeituraDocumento $falha): self
    {
        $codigo = array_key_exists($falha->codigo, self::MENSAGENS) ? $falha->codigo : 'erro_interno';

        return new self(
            categoria: array_key_exists($falha->categoria, self::CATEGORIAS) ? $falha->categoria : 'servico',
            codigos: [$codigo],
            recapturar: $falha->categoria === 'captura',
        );
    }

    public static function servico(string $codigo): self
    {
        return new self('servico', [$codigo]);
    }

    public function concluida(): bool
    {
        return in_array($this->categoria, ['ok', 'parcial', 'validacao'], true);
    }

    public function titulo(): string
    {
        return self::CATEGORIAS[$this->categoria];
    }

    /**
     * Orientações objetivas, na ordem: o que impediu a leitura, depois o
     * que melhorar na foto.
     *
     * @return list<string>
     */
    public function motivos(): array
    {
        $codigos = array_values(array_unique([...$this->codigos, ...$this->avisosQualidade]));

        return array_values(array_map(fn (string $codigo) => self::MENSAGENS[$codigo], $codigos));
    }

    /** Resumo sem dado pessoal, para log técnico. */
    public function paraLog(): array
    {
        return [
            'categoria' => $this->categoria,
            'codigos' => $this->codigos,
            'qualidade' => $this->avisosQualidade,
            'recapturar' => $this->recapturar,
            'orientacao_corrigida' => $this->orientacaoCorrigida,
        ];
    }

    /** @return array{categoria: string, titulo: string, motivos: list<string>, recapturar: bool} */
    public function paraTela(): array
    {
        return ['categoria' => $this->categoria, 'titulo' => $this->titulo(), 'motivos' => $this->motivos(), 'recapturar' => $this->recapturar];
    }

    /** @return list<string> */
    private static function codigosConhecidos(mixed $codigos): array
    {
        return array_values(array_filter(
            is_array($codigos) ? $codigos : [],
            fn ($codigo) => is_string($codigo) && array_key_exists($codigo, self::MENSAGENS),
        ));
    }
}
