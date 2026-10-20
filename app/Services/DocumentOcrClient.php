<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DocumentOcrClient
{
    public function enabled(): bool
    {
        return (bool) config('services.document_ocr.enabled');
    }

    /** @return array<string, mixed> */
    public function extract(
        string $documentType,
        UploadedFile $front,
        ?UploadedFile $back = null,
        bool $includePortrait = false,
    ): array {
        return $this->extractContents($documentType, $this->contents($front), $back ? $this->contents($back) : null, $includePortrait);
    }

    /**
     * Mesma leitura a partir do conteúdo binário das imagens (cadastro rápido
     * por câmera, que não guarda arquivo).
     *
     * @return array<string, mixed>
     */
    public function extractContents(string $documentType, string $front, ?string $back = null, bool $includePortrait = false): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('O leitor automático não está habilitado.');
        }

        $url = rtrim((string) config('services.document_ocr.url'), '/');
        if ($url === '') {
            throw new RuntimeException('O endereço interno do leitor automático não foi configurado.');
        }

        $request = $this->request()
            ->attach('front', $front, 'front.'.$this->extension($front), ['Content-Type' => $this->mimeType($front)]);

        if ($back !== null) {
            $request = $request->attach('back', $back, 'back.'.$this->extension($back), ['Content-Type' => $this->mimeType($back)]);
        }

        $response = $request->post($url.'/v1/extract', [
            'document_type' => $documentType,
            'include_portrait' => $includePortrait ? 'true' : 'false',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('O leitor automático não conseguiu processar o documento.');
        }

        $result = $response->json();
        if (! is_array($result) || ($result['status'] ?? null) !== 'completed' || ! is_array($result['fields'] ?? null)) {
            throw new RuntimeException('O leitor automático retornou um resultado inválido.');
        }

        return $result;
    }

    public function portraitBytes(array $result): ?string
    {
        $portrait = $result['portrait'] ?? null;
        if (! is_array($portrait) || ($portrait['detected'] ?? false) !== true) {
            return null;
        }

        $encoded = $portrait['image_base64'] ?? null;
        if (! is_string($encoded) || $encoded === '' || strlen($encoded) > 3_000_000) {
            throw new RuntimeException('O recorte retornado pelo leitor é inválido.');
        }

        $contents = base64_decode($encoded, true);
        if ($contents === false || $contents === '' || strlen($contents) > 2_000_000) {
            throw new RuntimeException('O recorte retornado pelo leitor é inválido.');
        }

        return $contents;
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout(max(1, (int) config('services.document_ocr.timeout', 15)))
            ->retry(1, 200, throw: false);

        $token = (string) config('services.document_ocr.token');

        return $token !== '' ? $request->withToken($token) : $request;
    }

    private function mimeType(string $contents): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
    }

    private function extension(string $contents): string
    {
        return match ($this->mimeType($contents)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function contents(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Não foi possível preparar a imagem para leitura.');
        }

        return $contents;
    }
}
