<?php

namespace App\Services\PreCadastro;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Link divulgado a todos para o pré-cadastro no portal público (decisão do PO, 11/11/2026).
 * O código do convite vai no fragmento (#convite=...) e funciona como chave compartilhada:
 * trocá-lo no portal invalida o link antigo. Fica no .env, nunca no Git.
 */
class LinkPublico
{
    public function url(): ?string
    {
        $link = trim((string) config('pre_cadastro_nuvem.link_publico'));
        $partes = parse_url($link);
        if (! is_array($partes) || ($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])
            || isset($partes['user']) || isset($partes['pass'])
            || ! preg_match('/^convite=[A-Za-z0-9_-]{32,128}$/', $partes['fragment'] ?? '')) {
            return null;
        }

        return $link;
    }

    /** QR code em SVG, sem serviço externo. */
    public function qrSvg(int $tamanho = 320): ?string
    {
        $url = $this->url();
        if ($url === null) {
            return null;
        }
        $svg = (new Writer(new ImageRenderer(new RendererStyle($tamanho, 2), new SvgImageBackEnd)))->writeString($url);

        return trim((string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg));
    }
}
