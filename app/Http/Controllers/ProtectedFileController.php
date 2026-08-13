<?php

namespace App\Http\Controllers;

use App\Models\Arquivo;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtectedFileController extends Controller
{
    public function __invoke(Arquivo $arquivo, AuditService $audit): StreamedResponse
    {
        Gate::authorize('view', $arquivo);

        $link = $arquivo->preRegistrationLinks()
            ->where('is_current', true)
            ->with('preRegistration')
            ->firstOrFail();

        if (! $arquivo->isAvailable()) {
            $audit->record(
                action: 'visualizou_arquivo_protegido',
                module: 'pre-cadastro',
                entityType: 'arquivos',
                entityId: $arquivo->id,
                result: 'negado',
                reasonCode: 'arquivo_indisponivel',
                classification: 'restrita',
                metadata: ['category' => $link->category, 'pre_registration_id' => $link->pre_registration_id],
            );

            abort(404);
        }

        $disk = Storage::disk($arquivo->disk);

        if (! $disk->exists($arquivo->object_key)) {
            $audit->record(
                action: 'visualizou_arquivo_protegido',
                module: 'pre-cadastro',
                entityType: 'arquivos',
                entityId: $arquivo->id,
                result: 'falha',
                reasonCode: 'objeto_nao_encontrado',
                classification: 'restrita',
                metadata: ['category' => $link->category, 'pre_registration_id' => $link->pre_registration_id],
            );

            abort(404);
        }

        $audit->record(
            action: 'visualizou_arquivo_protegido',
            module: 'pre-cadastro',
            entityType: 'arquivos',
            entityId: $arquivo->id,
            classification: 'restrita',
            metadata: [
                'category' => $link->category,
                'pre_registration_id' => $link->pre_registration_id,
                'purpose' => 'conferencia_identidade',
            ],
        );

        return $disk->response(
            $arquivo->object_key,
            $link->category.'.'.$arquivo->extension,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'inline',
        );
    }
}
