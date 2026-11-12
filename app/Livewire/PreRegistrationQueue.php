<?php

namespace App\Livewire;

use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\AuditService;
use App\Services\Hibrido\PreCadastroNuvem;
use App\Services\Hibrido\SincronizacaoIndisponivel;
use App\Services\PreCadastro\LinkPublico;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Fila de pré-cadastros. A análise de cada solicitação acontece na página
 * própria (PreRegistrationReview) desde o AJ-006.
 */
class PreRegistrationQueue extends Component
{
    /** Nomes de campo legíveis para a sinalização das falhas do portal. */
    private const CAMPOS = [
        'name' => 'nome', 'document' => 'CPF', 'document_type' => 'tipo de documento', 'birth_date' => 'nascimento',
        'phone' => 'telefone', 'email' => 'e-mail', 'access_type' => 'tipo de acesso', 'destination_property' => 'imóvel',
        'destination_label' => 'destino', 'responsible_name' => 'responsável', 'visit_date' => 'data da visita',
        'vehicle_plate' => 'placa', 'implantacao_id' => 'implantação', 'submitted_at' => 'data do envio',
        'category' => 'imagens', 'sha256' => 'imagens', 'size' => 'imagens',
    ];

    public string $statusFilter = 'aguardando';

    /** @var array{variant:string,title:string,message:string}|null */
    public ?array $resultadoBusca = null;

    public string $search = '';

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['aguardando', 'aprovado', 'correcao', 'rejeitado', 'todos'], true)) {
            $this->statusFilter = $status;
        }
    }

    /** @return Builder<PreRegistration> */
    private function baseQuery(): Builder
    {
        return PreRegistration::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('protocol', 'like', $term)
                        ->orWhere('vehicle_plate', 'like', $term)
                        ->orWhere('destination_label', 'like', $term);
                });
            })
            ->orderBy('submitted_at');
    }

    /** @return Collection<int, PreRegistration> */
    public function allRecords(): Collection
    {
        return $this->baseQuery()->get();
    }

    /** @return Collection<int, PreRegistration> */
    public function filteredRecords(): Collection
    {
        return $this->baseQuery()
            ->with('fileLinks.file')
            ->when($this->statusFilter !== 'todos', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->get();
    }

    /**
     * Foto de cada linha: a selfie vigente (do solicitante ou tirada pela
     * portaria). Sem a permissão de imagens sensíveis, nenhuma URL é gerada.
     *
     * @param  Collection<int, PreRegistration>  $records
     * @return array<string, array{tem: bool, url: ?string}>
     */
    private function fotos(Collection $records): array
    {
        $podeVer = (bool) Auth::user()?->hasPermission('arquivos.sensiveis.visualizar');
        $fotos = [];

        foreach ($records as $record) {
            $arquivo = $record->currentFileLink('selfie')?->file;
            $fotos[$record->id] = [
                'tem' => $arquivo !== null,
                'url' => $podeVer && $arquivo?->isAvailable()
                    ? route('protected-files.show', ['arquivo' => $arquivo, 'contexto' => 'fila_pre_cadastro'])
                    : null,
            ];
        }

        return $fotos;
    }

    /**
     * Envios do portal público que a importação recusou várias vezes (docs/023 §12).
     * Só códigos e nomes de campos; o conteúdo continua na nuvem até a reabertura.
     *
     * @return list<array{id:string,protocolo:?string,motivo:string,campos:list<string>,tentativas:int,situacao:string,quando:?string}>
     */
    public function falhasDoPortal(): array
    {
        return DB::table('pre_cadastro_nuvem_falhas')
            ->where('implantacao_id', Implantacao::current()->id)
            ->whereIn('situacao', ['conferencia', 'reabertura_solicitada'])
            ->orderBy('ultima_falha_em')->limit(50)->get()
            ->map(fn ($falha) => [
                'id' => (string) $falha->id,
                'protocolo' => $falha->protocol,
                'motivo' => PreCadastroNuvem::CODIGOS[$falha->codigo] ?? 'Falha não identificada',
                'campos' => array_values(array_unique(array_map(
                    fn (string $campo) => self::CAMPOS[preg_replace('/^(dados|files)\.(\d+\.)?/', '', $campo)] ?? $campo,
                    array_filter((array) json_decode((string) $falha->campos, true), 'is_string'),
                ))),
                'tentativas' => (int) $falha->tentativas,
                'situacao' => (string) $falha->situacao,
                'quando' => $falha->ultima_falha_em,
            ])->all();
    }

    /**
     * Busca na hora os envios do portal, quando a busca automática ainda não trouxe.
     * Mesmo serviço do agendador: não aprova, não libera e não envia à facial.
     */
    public function buscarDaNuvem(): void
    {
        abort_unless((bool) Auth::user()?->hasPermission('pre-registro.editar'), 403);

        $chave = 'pre-cadastro-nuvem:busca-manual:'.Implantacao::current()->id;
        if (RateLimiter::tooManyAttempts($chave, 1)) {
            $this->resultadoBusca = ['variant' => 'info', 'title' => 'Busca recente', 'message' => 'Aguarde '.RateLimiter::availableIn($chave).' segundos para buscar de novo.'];

            return;
        }
        RateLimiter::hit($chave, 15);

        try {
            $totais = app(PreCadastroNuvem::class)->sincronizar();
        } catch (SincronizacaoIndisponivel $erro) {
            $this->resultadoBusca = ['variant' => 'warning', 'title' => 'Busca não concluída', 'message' => $erro->getMessage()];

            return;
        } catch (\Throwable) {
            $this->resultadoBusca = ['variant' => 'warning', 'title' => 'Busca não concluída', 'message' => 'Confira a internet e a configuração. Os registros locais foram preservados.'];

            return;
        }

        app(AuditService::class)->record(action: 'buscou_pre_cadastros_nuvem', module: 'pre-cadastro', entityType: 'pre_cadastro_nuvem',
            classification: 'interna', metadata: $totais);

        $partes = [$totais['importados'] === 1 ? '1 novo pré-cadastro recebido' : $totais['importados'].' novos pré-cadastros recebidos'];
        if ($totais['falhas'] > 0) {
            $partes[] = $totais['falhas'].' com pendência (nova tentativa automática)';
        }
        if ($totais['conferencia'] > 0) {
            $partes[] = $totais['conferencia'].' enviado(s) para conferência';
        }
        $this->resultadoBusca = [
            'variant' => $totais['falhas'] > 0 ? 'warning' : 'success',
            'title' => $totais['importados'] > 0 ? 'Busca concluída' : 'Nenhum envio novo',
            'message' => implode('; ', $partes).'.',
        ];
    }

    /** Devolve a solicitação à fila da nuvem na próxima sincronização. Não aprova nem libera nada. */
    public function tentarNovamente(string $falhaId): void
    {
        abort_unless((bool) Auth::user()?->hasPermission('pre-registro.editar'), 403);

        $alteradas = DB::table('pre_cadastro_nuvem_falhas')
            ->where('implantacao_id', Implantacao::current()->id)
            ->where('id', $falhaId)->where('situacao', 'conferencia')
            ->update(['situacao' => 'reabertura_solicitada', 'reabertura_solicitada_por' => Auth::id(), 'updated_at' => now()]);

        if ($alteradas === 1) {
            app(AuditService::class)->record(action: 'solicitou_nova_importacao_pre_cadastro_nuvem', module: 'pre-cadastro',
                entityType: 'pre_cadastro_nuvem_falhas', entityId: $falhaId, classification: 'restrita');
        }
    }

    public function render(): View
    {
        $filteredRecords = $this->filteredRecords();

        return view('livewire.pre-registration-queue', [
            'records' => $this->allRecords(),
            'filteredRecords' => $filteredRecords,
            'fotos' => $this->fotos($filteredRecords),
            'podeTirarFoto' => (bool) Auth::user()?->hasPermission('pre-registro.editar'),
            'falhasPortal' => $this->falhasDoPortal(),
            'podeReabrir' => (bool) Auth::user()?->hasPermission('pre-registro.editar'),
            'linkPublico' => app(LinkPublico::class)->url(),
            'podeBuscarNuvem' => (bool) config('pre_cadastro_nuvem.enabled') && (bool) Auth::user()?->hasPermission('pre-registro.editar'),
        ])->layout('components.layouts.app', [
            'title' => 'Pré-cadastros',
            'heading' => 'Pré-cadastros',
            'headingDescription' => 'Solicitações antecipadas aguardando análise da portaria',
        ]);
    }
}
