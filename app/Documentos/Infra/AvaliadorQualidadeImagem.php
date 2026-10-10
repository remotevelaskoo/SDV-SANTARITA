<?php

namespace App\Documentos\Infra;

/**
 * Avaliação de qualidade da captura antes do OCR (SDV-OPE-018 §7), em
 * memória, com GD. Mede resolução, iluminação, reflexo, foco, movimento e
 * enquadramento (bordas). Inclinação, obstrução e face esperada dependem
 * do motor de OCR real e são devolvidas como "não avaliadas".
 *
 * Os limiares ficam em `documentos.qualidade` e são provisórios até a
 * homologação das câmeras (SDV-OPE-018 §11).
 */
class AvaliadorQualidadeImagem
{
    public const MENSAGENS = [
        'imagem_invalida' => 'Documento não identificado. Faça nova captura ou digite os dados.',
        'resolucao_baixa' => 'Resolução baixa. Aproxime o documento ou use a câmera em resolução maior.',
        'escura' => 'Imagem escura. Melhore a iluminação e capture novamente.',
        'clara_demais' => 'Imagem clara demais. Reduza a luz direta e capture novamente.',
        'reflexo' => 'Reflexo detectado. Altere o ângulo ou a iluminação.',
        'desfocada' => 'Imagem desfocada. Aproxime o documento e capture novamente.',
        'movimento' => 'Imagem tremida. Mantenha o documento parado e capture novamente.',
        'cortado' => 'Documento cortado. Posicione as quatro bordas dentro da moldura.',
        'nao_identificado' => 'Documento não identificado. Faça nova captura ou digite os dados.',
    ];

    /** Critérios que esta versão não mede (dependem do OCR real). */
    public const NAO_AVALIADOS = ['inclinacao', 'obstrucao', 'face_esperada'];

    /**
     * @return array{aprovada: bool, problemas: array<string, string>, nao_avaliados: list<string>, metricas: array<string, float|int>}
     */
    public function avaliar(string $conteudo): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['aprovada' => true, 'problemas' => [], 'nao_avaliados' => ['qualidade_sem_gd', ...self::NAO_AVALIADOS], 'metricas' => []];
        }

        $imagem = @imagecreatefromstring($conteudo);
        if ($imagem === false) {
            return $this->resultado(['imagem_invalida'], []);
        }

        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $cinza = $this->tonsDeCinza($imagem, 320);

        $c = (array) config('documentos.qualidade');
        $m = $this->metricas($cinza);
        $m['largura'] = $largura;
        $m['altura'] = $altura;

        $problemas = [];
        if (min($largura, $altura) < (int) ($c['resolucao_minima'] ?? 480)) {
            $problemas[] = 'resolucao_baixa';
        }
        if ($m['brilho'] < (float) ($c['brilho_minimo'] ?? 50)) {
            $problemas[] = 'escura';
        } elseif ($m['brilho'] > (float) ($c['brilho_maximo'] ?? 225)) {
            $problemas[] = 'clara_demais';
        }
        if ($m['reflexo'] > (float) ($c['reflexo_maximo'] ?? 0.04)) {
            $problemas[] = 'reflexo';
        }
        if ($m['bordas_total'] < (float) ($c['bordas_minimas'] ?? 0.01)) {
            $problemas[] = 'nao_identificado';
        } else {
            if ($m['nitidez'] < (float) ($c['nitidez_minima'] ?? 300)) {
                $problemas[] = 'desfocada';
            } elseif ($m['direcionalidade'] > (float) ($c['direcionalidade_maxima'] ?? 4)) {
                $problemas[] = 'movimento';
            }
            if ($m['bordas_na_margem'] > (float) ($c['bordas_na_margem_maxima'] ?? 0.12)) {
                $problemas[] = 'cortado';
            }
        }

        return $this->resultado($problemas, $m);
    }

    /**
     * @param  list<string>  $codigos
     * @param  array<string, float|int>  $metricas
     */
    private function resultado(array $codigos, array $metricas): array
    {
        $problemas = [];
        foreach ($codigos as $codigo) {
            $problemas[$codigo] = self::MENSAGENS[$codigo];
        }

        return ['aprovada' => $problemas === [], 'problemas' => $problemas, 'nao_avaliados' => self::NAO_AVALIADOS, 'metricas' => $metricas];
    }

    /** @return list<list<int>> matriz de luminância reduzida */
    private function tonsDeCinza(\GdImage $imagem, int $larguraAlvo): array
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $escala = min(1, $larguraAlvo / max(1, $largura));
        $w = max(8, (int) round($largura * $escala));
        $h = max(8, (int) round($altura * $escala));

        $reduzida = imagecreatetruecolor($w, $h);
        imagecopyresampled($reduzida, $imagem, 0, 0, 0, 0, $w, $h, $largura, $altura);

        $matriz = [];
        for ($y = 0; $y < $h; $y++) {
            $linha = [];
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($reduzida, $x, $y);
                $linha[] = (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
            }
            $matriz[] = $linha;
        }

        return $matriz;
    }

    /**
     * @param  list<list<int>>  $g
     * @return array{brilho: float, reflexo: float, nitidez: float, direcionalidade: float, bordas_total: float, bordas_na_margem: float}
     */
    private function metricas(array $g): array
    {
        $h = count($g);
        $w = count($g[0]);
        $soma = 0;
        $saturados = 0;
        $laplacianos = [];
        $gx = 0.0;
        $gy = 0.0;
        $bordas = 0;
        $bordasMargem = 0;
        $pixelsMargem = 0;
        $margem = max(1, (int) round(min($w, $h) * 0.03));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $v = $g[$y][$x];
                $soma += $v;
                if ($v >= 250) {
                    $saturados++;
                }
                $naMargem = $x < $margem || $y < $margem || $x >= $w - $margem || $y >= $h - $margem;
                if ($naMargem) {
                    $pixelsMargem++;
                }
                if ($x === 0 || $y === 0 || $x === $w - 1 || $y === $h - 1) {
                    continue;
                }

                $laplacianos[] = $g[$y - 1][$x] + $g[$y + 1][$x] + $g[$y][$x - 1] + $g[$y][$x + 1] - 4 * $v;
                $dx = abs($g[$y][$x + 1] - $g[$y][$x - 1]);
                $dy = abs($g[$y + 1][$x] - $g[$y - 1][$x]);
                $gx += $dx;
                $gy += $dy;
                if ($dx + $dy > 60) {
                    $bordas++;
                    if ($naMargem) {
                        $bordasMargem++;
                    }
                }
            }
        }

        $total = $w * $h;
        $media = array_sum($laplacianos) / max(1, count($laplacianos));
        $variancia = 0.0;
        foreach ($laplacianos as $l) {
            $variancia += ($l - $media) ** 2;
        }

        return [
            'brilho' => $soma / $total,
            'reflexo' => $saturados / $total,
            'nitidez' => $variancia / max(1, count($laplacianos)),
            'direcionalidade' => max($gx, $gy) / max(1.0, min($gx, $gy)),
            'bordas_total' => $bordas / $total,
            'bordas_na_margem' => $bordasMargem / max(1, $pixelsMargem),
        ];
    }
}
