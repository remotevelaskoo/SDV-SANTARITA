<?php

namespace App\Http\Controllers;

use App\Models\Arquivo;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtectedFileController extends Controller
{
    /** Contextos aceitos no parâmetro `contexto`, gravados na auditoria (AJ-006). */
    private const CONTEXTOS = ['busca_validacao', 'validacao', 'analise_pre_cadastro', 'evidencia_placa'];

    public function __invoke(Request $request, Arquivo $arquivo, AuditService $audit): StreamedResponse
    {
        Gate::authorize('view', $arquivo);

        [$module, $category, $metadata] = $this->vinculo($arquivo);
        $contexto = in_array($request->query('contexto'), self::CONTEXTOS, true) ? $request->query('contexto') : null;
        if ($contexto !== null) {
            $metadata['contexto'] = $contexto;
        }

        if (! $arquivo->isAvailable()) {
            $audit->record(
                action: 'visualizou_arquivo_protegido',
                module: $module,
                entityType: 'arquivos',
                entityId: $arquivo->id,
                result: 'negado',
                reasonCode: 'arquivo_indisponivel',
                classification: 'restrita',
                metadata: $metadata,
            );

            abort(404);
        }

        $disk = Storage::disk($arquivo->disk);

        if (! $disk->exists($arquivo->object_key)) {
            $audit->record(
                action: 'visualizou_arquivo_protegido',
                module: $module,
                entityType: 'arquivos',
                entityId: $arquivo->id,
                result: 'falha',
                reasonCode: 'objeto_nao_encontrado',
                classification: 'restrita',
                metadata: $metadata,
            );

            abort(404);
        }

        $audit->record(
            action: 'visualizou_arquivo_protegido',
            module: $module,
            entityType: 'arquivos',
            entityId: $arquivo->id,
            classification: 'restrita',
            metadata: [...$metadata, 'purpose' => 'conferencia_identidade'],
        );

        return $disk->response(
            $arquivo->object_key,
            $category.'.'.$arquivo->extension,
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

    /**
     * O arquivo só é servido quando está ligado, como versão vigente, a um
     * pré-cadastro ou a uma pessoa da implantação, ou quando é a evidência de
     * uma captura de placa.
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function vinculo(Arquivo $arquivo): array
    {
        $preCadastro = $arquivo->preRegistrationLinks()->where('is_current', true)->first();
        if ($preCadastro !== null) {
            return ['pre-cadastro', $preCadastro->category, ['category' => $preCadastro->category, 'pre_registration_id' => $preCadastro->pre_registration_id]];
        }

        $pessoa = $arquivo->pessoaLinks()->where('is_current', true)->first();
        if ($pessoa !== null) {
            return ['pessoas', $pessoa->category, ['category' => $pessoa->category, 'pessoa_id' => $pessoa->pessoa_id]];
        }

        $captura = $arquivo->capturaPlaca;
        if ($captura !== null) {
            return ['validacao', 'evidencia_placa', ['category' => 'evidencia_placa', 'captura_placa_id' => $captura->id, 'simulada' => $captura->simulada()]];
        }

        abort(404);
    }
}
