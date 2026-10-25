<?php

namespace App\Services;

use App\Models\Arquivo;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
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
     * @param  array<'documento'|'documento_verso'|'documento_foto'|'selfie', UploadedFile>  $uploads
     * @return list<Arquivo>
     */
    public function storePreRegistrationFiles(PreRegistration $preRegistration, array $uploads): array
    {
        $storedObjects = [];
        $prepared = [];

        try {
            foreach ($uploads as $category => $upload) {
                if (! in_array($category, ['documento', 'documento_verso', 'documento_foto', 'selfie'], true)) {
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
                    'display_name' => match ($category) {
                        'documento' => 'Frente do documento',
                        'documento_verso' => 'Verso do documento',
                        'documento_foto' => 'Foto localizada no documento',
                        default => 'Selfie enviada',
                    },
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
                        'classification' => in_array($metadata['category'], ['selfie', 'documento_foto'], true) ? 'sensivel' : 'pessoal',
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

    public function storeGeneratedPortrait(PreRegistration $preRegistration, string $contents): Arquivo
    {
        if ($contents === '' || strlen($contents) > 2_000_000) {
            throw new RuntimeException('O recorte da foto possui tamanho inválido.');
        }

        $image = @getimagesizefromstring($contents);
        if (! is_array($image) || ($image['mime'] ?? null) !== 'image/jpeg') {
            throw new RuntimeException('O recorte da foto não é uma imagem JPEG válida.');
        }

        $temporaryPath = tempnam(storage_path('framework/cache'), 'sdv-portrait-');
        if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
            throw new RuntimeException('Não foi possível preparar o recorte para armazenamento privado.');
        }

        try {
            $upload = new UploadedFile(
                $temporaryPath,
                'foto-documento.jpg',
                'image/jpeg',
                null,
                true,
            );

            [$file] = $this->storePreRegistrationFiles($preRegistration, [
                'documento_foto' => $upload,
            ]);

            return $file;
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * Foto protegida da pessoa (AJ-006). Nova versão a cada envio; a anterior
     * deixa de ser vigente, mas o objeto e o catálogo são preservados.
     */
    public function storePessoaFoto(Pessoa $pessoa, UploadedFile $upload, string $origin = 'cadastro_pessoa'): Arquivo
    {
        $metadata = $this->storeObject($pessoa->implantacao_id, 'foto_pessoa', $upload);

        try {
            return DB::transaction(function () use ($pessoa, $metadata, $origin): Arquivo {
                PessoaArquivo::query()
                    ->where('pessoa_id', $pessoa->id)
                    ->where('category', 'foto')
                    ->where('is_current', true)
                    ->update(['is_current' => false, 'replaced_at' => now()]);

                $file = Arquivo::query()->create([
                    ...$metadata,
                    'display_name' => 'Foto da pessoa',
                    'classification' => 'sensivel',
                    'purpose' => 'identificacao_pessoa',
                    'status' => 'disponivel',
                    'origin' => $origin,
                    'created_by' => Auth::id(),
                ]);

                PessoaArquivo::query()->create([
                    'pessoa_id' => $pessoa->id,
                    'arquivo_id' => $file->id,
                    'category' => 'foto',
                    'is_current' => true,
                    'linked_at' => now(),
                ]);

                $this->audit->record(
                    action: 'recebeu_arquivos_protegidos',
                    module: 'pessoas',
                    entityType: 'pessoas',
                    entityId: $pessoa->id,
                    classification: 'restrita',
                    metadata: ['categories' => ['foto'], 'quantity' => 1, 'origin' => $origin],
                );

                return $file;
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($metadata['object_key']);

            throw $exception;
        }
    }

    /**
     * Imagem da câmera de placas como evidência privada (AJ-006). Só o
     * catálogo é criado aqui; a captura que aponta para ele fica com o
     * serviço de câmeras, na mesma transação.
     */
    public function storeEvidenciaPlaca(string $implantacaoId, string $contents, string $origin): Arquivo
    {
        $image = @getimagesizefromstring($contents);
        if ($contents === '' || ! is_array($image) || ! in_array($image['mime'] ?? null, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('A imagem da câmera não é um JPEG ou PNG válido.');
        }

        $extension = self::EXTENSIONS_BY_MIME[$image['mime']];
        $temporaryPath = tempnam(storage_path('framework/cache'), 'sdv-placa-');
        if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
            throw new RuntimeException('Não foi possível preparar a imagem para armazenamento privado.');
        }

        try {
            $metadata = $this->storeObject($implantacaoId, 'evidencia_placa', new UploadedFile($temporaryPath, 'placa.'.$extension, $image['mime'], null, true));
        } finally {
            @unlink($temporaryPath);
        }

        return Arquivo::query()->create([
            ...$metadata,
            'display_name' => 'Imagem da placa',
            'classification' => 'sensivel',
            'purpose' => 'evidencia_placa',
            'status' => 'disponivel',
            'origin' => $origin,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Grava o binário no disco privado e devolve o catálogo para `arquivos`.
     * Usado pelos fluxos novos; o pré-cadastro mantém o próprio método.
     *
     * @return array{disk: string, object_key: string, original_name: string, extension: string, declared_mime: ?string, detected_mime: string, size_bytes: int, checksum_sha256: string}
     */
    public function storeObject(string $implantacaoId, string $category, UploadedFile $upload): array
    {
        $detectedMime = (string) $upload->getMimeType();
        $extension = self::EXTENSIONS_BY_MIME[$detectedMime] ?? null;

        if ($extension === null) {
            throw new RuntimeException('O conteúdo do arquivo não corresponde a uma imagem permitida.');
        }

        $checksum = hash_file('sha256', $upload->getRealPath());
        $size = $upload->getSize();

        if ($checksum === false || $size === false) {
            throw new RuntimeException('Não foi possível conferir a integridade do arquivo recebido.');
        }

        $objectKey = implode('/', [
            app()->environment(),
            $implantacaoId,
            $category,
            now()->format('Y/m'),
            (string) Str::uuid7().'.'.$extension,
        ]);

        $stream = fopen($upload->getRealPath(), 'rb');

        if ($stream === false) {
            throw new RuntimeException('Não foi possível ler o arquivo recebido.');
        }

        try {
            $written = Storage::disk(self::DISK)->put($objectKey, $stream, [
                'visibility' => 'private',
                'ContentType' => $detectedMime,
            ]);
        } finally {
            fclose($stream);
        }

        if (! $written) {
            throw new RuntimeException('Não foi possível armazenar o arquivo de forma privada.');
        }

        return [
            'disk' => self::DISK,
            'object_key' => $objectKey,
            'original_name' => $upload->getClientOriginalName(),
            'extension' => $extension,
            'declared_mime' => $upload->getClientMimeType(),
            'detected_mime' => $detectedMime,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
        ];
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
