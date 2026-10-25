<?php

namespace App\Services;

use App\Models\Arquivo;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Support\NormalizadorBusca;
use Illuminate\Support\Collection;

/**
 * Encontra a foto real e protegida de uma pessoa (AJ-006, fatia 1).
 *
 * Ordem: a foto do cadastro da pessoa (`pessoa_arquivos`) e, na falta dela,
 * a selfie do pré-cadastro aprovado com o mesmo documento (mesma ponte do
 * AJ-001). A foto serve só para conferência humana: não é credencial nem
 * biometria (ADR-013).
 */
class FotoPessoaResolver
{
    /**
     * @param  Collection<int, Pessoa>  $pessoas
     * @return array<string, array{arquivo: Arquivo, origem: string}>
     */
    public function paraPessoas(Collection $pessoas): array
    {
        $ids = $pessoas->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $fotos = [];

        PessoaArquivo::query()
            ->whereIn('pessoa_id', $ids)
            ->where('category', 'foto')
            ->where('is_current', true)
            ->with('file')
            ->get()
            ->each(function (PessoaArquivo $link) use (&$fotos): void {
                if ($link->file?->isAvailable()) {
                    $fotos[$link->pessoa_id] = ['arquivo' => $link->file, 'origem' => 'cadastro'];
                }
            });

        $semFoto = array_values(array_diff($ids, array_keys($fotos)));
        if ($semFoto === []) {
            return $fotos;
        }

        // Documento apresentado → pessoa, para cruzar com o pré-cadastro aprovado.
        $porDocumento = [];
        PessoaDocumento::query()
            ->whereIn('pessoa_id', $semFoto)
            ->where('status', 'ativo')
            ->whereNull('ended_at')
            ->get(['pessoa_id', 'valor_apresentacao', 'valor_normalizado'])
            ->each(function (PessoaDocumento $documento) use (&$porDocumento): void {
                $porDocumento[$documento->valor_apresentacao] = $documento->pessoa_id;
                $porDocumento[$documento->valor_normalizado] = $documento->pessoa_id;
            });

        if ($porDocumento === []) {
            return $fotos;
        }

        PreRegistration::query()
            ->whereIn('document', array_keys($porDocumento))
            ->where('status', 'aprovado')
            ->with('fileLinks.file')
            ->orderByDesc('submitted_at')
            ->get()
            ->each(function (PreRegistration $preRegistration) use (&$fotos, $porDocumento): void {
                $pessoaId = $porDocumento[$preRegistration->document]
                    ?? $porDocumento[NormalizadorBusca::digitos($preRegistration->document)]
                    ?? null;

                if ($pessoaId === null || isset($fotos[$pessoaId])) {
                    return;
                }

                $selfie = $preRegistration->currentFileLink('selfie')?->file;
                if ($selfie?->isAvailable()) {
                    $fotos[$pessoaId] = ['arquivo' => $selfie, 'origem' => 'pre_cadastro'];
                }
            });

        return $fotos;
    }

    /** @return array{arquivo: Arquivo, origem: string}|null */
    public function paraPessoa(Pessoa $pessoa): ?array
    {
        return $this->paraPessoas(collect([$pessoa]))[$pessoa->id] ?? null;
    }
}
