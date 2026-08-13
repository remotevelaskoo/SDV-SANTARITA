<?php

namespace App\Services;

use App\Models\Arquivo;
use App\Models\PreRegistration;
use App\Models\PreRegistrationArquivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PrivateFileService
{
    public const DISK = 'private-files';

    /** @var array<string, string> */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private AuditService $audit) {}

    /**
     * @param  array<'documento'|'selfie', UploadedFile>  $uploads
     * @return list<Arquivo>
     */
    public function storePreRegistrationFiles(PreRegistration $preRegistration, array $uploads): array
    {
        $storedObjects = [];
        $prepared = [];

        try {
            foreach ($uploads as $category => $upload) {
                if (! in_array($category, ['documento', 'selfie'], true)) {
                    throw new RuntimeException('Categoria de arquivo não permitida.');
                }

                $detectedMime = (string) $upload->getMimeType();
                $extension = self::EXTENSIONS_BY_MIME[$detectedMime] ?? null;

                if ($extension === null) {
                    throw new RuntimeException('O conteúdo do arquivo não corresponde a uma imagem permitida.');
                }

                $objectKey = $this->objectKey($preRegistration, $category, $extension);
                $checksum = hash_file('sha256', $upload->getRealPath());
                $size = $upload->getSize();

                if ($checksum === false || $size === false) {
                    throw new RuntimeException('Não foi possível conferir a integridade do arquivo recebido.');
                }

                $stream = fopen($upload->getRealPath(), 'rb');

                if ($stream === false) {
                    throw new RuntimeException('Não foi possível ler o arquivo recebido.');
                }

                try {
                    $written = Storage::disk(self::DISK)->put($objectKey, $stream, [
                        'visibility' => 'private',
                        'ContentType' => $detectedMime,
                    ]);

                    if (! $written) {
                        throw new RuntimeException('Não foi possível armazenar o arquivo de forma privada.');
                    }
                } finally {
                    fclose($stream);
                }

                $storedObjects[] = $objectKey;
                $prepared[] = [
                    'category' => $category,
                    'object_key' => $objectKey,
                    'original_name' => $upload->getClientOriginalName(),
                    'display_name' => $category === 'documento' ? 'Documento enviado' : 'Selfie enviada',
                    'extension' => $extension,
                    'declared_mime' => $upload->getClientMimeType(),
                    'detected_mime' => $detectedMime,
                    'size_bytes' => $size,
                    'checksum_sha256' => $checksum,
                ];
            }

            return DB::transaction(function () use ($preRegistration, $prepared): array {
                $files = [];

                foreach ($prepared as $metadata) {
                    PreRegistrationArquivo::query()
                        ->where('pre_registration_id', $preRegistration->id)
                        ->where('category', $metadata['category'])
                        ->where('is_current', true)
                        ->update(['is_current' => false, 'replaced_at' => now()]);

                    $file = Arquivo::query()->create([
                        'disk' => self::DISK,
                        'object_key' => $metadata['object_key'],
                        'original_name' => $metadata['original_name'],
                        'display_name' => $metadata['display_name'],
                        'extension' => $metadata['extension'],
                        'declared_mime' => $metadata['declared_mime'],
                        'detected_mime' => $metadata['detected_mime'],
                        'size_bytes' => $metadata['size_bytes'],
                        'checksum_sha256' => $metadata['checksum_sha256'],
                        'classification' => $metadata['category'] === 'selfie' ? 'sensivel' : 'pessoal',
                        'purpose' => 'conferencia_pre_cadastro',
                        'status' => 'disponivel',
                        'origin' => 'pre_cadastro_publico',
                        'created_by' => Auth::id(),
                    ]);

                    PreRegistrationArquivo::query()->create([
                        'pre_registration_id' => $preRegistration->id,
                        'arquivo_id' => $file->id,
                        'category' => $metadata['category'],
                        'is_current' => true,
                        'linked_at' => now(),
                    ]);

                    $files[] = $file;
                }

                $this->audit->record(
                    action: 'recebeu_arquivos_protegidos',
                    module: 'pre-cadastro',
                    entityType: 'pre_registrations',
                    entityId: $preRegistration->id,
                    classification: 'restrita',
                    metadata: [
                        'categories' => array_column($prepared, 'category'),
                        'quantity' => count($prepared),
                    ],
                );

                return $files;
            });
        } catch (\Throwable $exception) {
            foreach ($storedObjects as $objectKey) {
                Storage::disk(self::DISK)->delete($objectKey);
            }

            throw $exception;
        }
    }

    private function objectKey(PreRegistration $preRegistration, string $category, string $extension): string
    {
        return implode('/', [
            app()->environment(),
            $preRegistration->implantacao_id,
            $category,
            now()->format('Y/m'),
            (string) Str::uuid7().'.'.$extension,
        ]);
    }
}
