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
    public function extract(string $documentType, UploadedFile $front, ?UploadedFile $back = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('O leitor automático não está habilitado.');
        }

        $url = rtrim((string) config('services.document_ocr.url'), '/');
        if ($url === '') {
            throw new RuntimeException('O endereço interno do leitor automático não foi configurado.');
        }

        $request = $this->request()
            ->attach('front', $this->contents($front), $front->getClientOriginalName(), [
                'Content-Type' => $front->getMimeType(),
            ]);

        if ($back !== null) {
            $request = $request->attach('back', $this->contents($back), $back->getClientOriginalName(), [
                'Content-Type' => $back->getMimeType(),
            ]);
        }

        $response = $request->post($url.'/v1/extract', [
            'document_type' => $documentType,
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

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout(max(1, (int) config('services.document_ocr.timeout', 15)))
            ->retry(1, 200, throw: false);

        $token = (string) config('services.document_ocr.token');

        return $token !== '' ? $request->withToken($token) : $request;
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
