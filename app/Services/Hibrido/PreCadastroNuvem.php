<?php

namespace App\Services\Hibrido;

use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\AuditService;
use App\Services\PreCadastro\CatalogoDestinos;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Ponte de saída: nunca abre uma porta da rede local para a internet. */
class PreCadastroNuvem
{
    private string $url;

    private string $token;

    /** Códigos sem dados pessoais trocados com a nuvem e mostrados na fila local. */
    public const CODIGOS = [
        'dados_invalidos' => 'Dados fora das regras atuais',
        'arquivos_incompletos' => 'Imagens obrigatórias ausentes',
        'arquivo_integridade' => 'Imagem não conferiu com o envio',
        'imagem_invalida' => 'Imagem ilegível ou fora do formato',
        'erro_interno' => 'Erro ao gravar no sistema local',
    ];

    public function __construct(private PrivateFileService $files, private AuditService $audit, private CatalogoDestinos $catalogo) {}

    /** @return array{importados:int,confirmados:int,falhas:int,conferencia:int} */
    public function sincronizar(): array
    {
        if (! config('pre_cadastro_nuvem.enabled')) {
            throw new SincronizacaoIndisponivel('Sincronização do pré-cadastro desativada.');
        }

        $this->url = rtrim((string) config('pre_cadastro_nuvem.url'), '/');
        $this->token = (string) config('pre_cadastro_nuvem.token');
        $parsed = parse_url($this->url);
        if (! is_array($parsed) || ($parsed['scheme'] ?? '') !== 'https' || empty($parsed['host'])
            || (isset($parsed['user']) || isset($parsed['pass'])) || isset($parsed['query']) || isset($parsed['fragment'])
            || ! in_array($parsed['path'] ?? '', ['', '/'], true) || strlen($this->token) < 32) {
            throw new SincronizacaoIndisponivel('Configure um endereço HTTPS e a credencial exclusiva do nó.');
        }

        $implantacao = Implantacao::query()->whereKey(config('pre_cadastro_nuvem.implantacao_id'))->where('status', 'ativa')->firstOrFail();
        $lock = Cache::lock('pre-cadastro-nuvem:'.$implantacao->id, 3600);
        if (! $lock->get()) {
            throw new SincronizacaoIndisponivel('Outra sincronização está em andamento.');
        }

        try {
            return ImplantacaoContext::executarNo($implantacao, fn () => $this->executar($implantacao));
        } finally {
            $lock->release();
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)->acceptJson()->connectTimeout(5)->timeout(45)
            ->withOptions(['allow_redirects' => false]);
    }

    /** @return array{importados:int,confirmados:int,falhas:int,conferencia:int} */
    private function executar(Implantacao $implantacao): array
    {
        $totais = ['importados' => 0, 'confirmados' => 0, 'falhas' => 0, 'conferencia' => 0];
        $this->publicarCatalogo($implantacao);
        // Retenta primeiro os ACKs e decisões locais ainda não confirmados na nuvem.
        foreach (DB::table('pre_cadastro_nuvem_recebimentos as l')
            ->join('pre_registrations as p', 'p.id', '=', 'l.pre_registration_id')
            ->where('l.implantacao_id', $implantacao->id)
            ->whereColumn('l.acked_version', '<', 'p.version')
            ->select('l.*')->orderBy('l.updated_at')->limit(10)->get() as $link) {
            try {
                $record = PreRegistration::query()->findOrFail($link->pre_registration_id);
                if ($link->acked_version < $record->version) {
                    $this->confirmar($link->cloud_id, $record);
                    $totais['confirmados']++;
                }
            } catch (\Throwable) {
                $totais['falhas']++;
            }
        }

        // Reabre na nuvem as solicitações que a portaria liberou para nova tentativa.
        foreach (DB::table('pre_cadastro_nuvem_falhas')->where('implantacao_id', $implantacao->id)
            ->where('situacao', 'reabertura_solicitada')->limit(10)->get() as $falha) {
            $response = $this->http()->post($this->url.'/api/sync', ['action' => 'reabrir', 'id' => $falha->cloud_id]);
            if ($response->successful()) {
                DB::table('pre_cadastro_nuvem_falhas')->where('id', $falha->id)
                    ->update(['situacao' => 'retentando', 'tentativas' => 0, 'updated_at' => now()]);
            } else {
                $totais['falhas']++;
            }
        }

        $response = $this->http()->get($this->url.'/api/sync', ['action' => 'pull']);
        if (! $response->successful()) {
            throw new SincronizacaoIndisponivel('Nuvem indisponível; os dados locais e as confirmações pendentes foram preservados.');
        }
        $records = $response->json('records');
        if (! is_array($records) || count($records) > 10) {
            throw new SincronizacaoIndisponivel('Resposta inválida do portal.');
        }

        foreach ($records as $item) {
            try {
                $record = $this->importar($implantacao, $item);
            } catch (\Throwable $error) {
                // Não registrar payload, documento, foto, token nem mensagem de terceiros.
                $totais['falhas']++;
                if ($this->registrarFalha($implantacao, $item, $error) === 'conferencia') {
                    $totais['conferencia']++;
                }

                continue;
            }
            $totais['importados']++;
            DB::table('pre_cadastro_nuvem_falhas')->where('implantacao_id', $implantacao->id)->where('cloud_id', $item['id'])
                ->where('situacao', '!=', 'resolvida')->update(['situacao' => 'resolvida', 'resolvida_em' => now(), 'updated_at' => now()]);
            try {
                $this->confirmar($item['id'], $record);
                $totais['confirmados']++;
            } catch (\Throwable) {
                $totais['falhas']++;
            }
        }

        return $totais;
    }

    private function importar(Implantacao $implantacao, mixed $item): PreRegistration
    {
        $data = Validator::make(is_array($item) ? $item : [], [
            'id' => ['required', 'uuid'],
            'implantacao_id' => ['required', Rule::in([$implantacao->id])],
            'protocol' => ['required', 'string', 'max:80', 'regex:/^PRE-SRA-[A-F0-9]{32}$/'],
            // Tolerância para pequena diferença entre o relógio da nuvem e o da portaria.
            'submitted_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'dados' => ['required', 'array:name,document,document_type,birth_date,phone,email,access_type,address_informed,destination_property,destination_label,responsible_name,visit_date,vehicle_plate,vehicle_model,vehicle_color'],
            'dados.name' => ['required', 'string', 'min:3', 'max:120'],
            'dados.document' => ['required', 'regex:/^\d{11}$/'],
            'dados.document_type' => ['required', Rule::in(['cnh', 'rg_cin'])],
            'dados.birth_date' => ['nullable', 'date', 'before:today'],
            'dados.phone' => ['nullable', 'string', 'max:20'],
            'dados.email' => ['nullable', 'email', 'max:120'],
            'dados.access_type' => ['required', Rule::in(PreRegistration::ACCESS_TYPES)],
            'dados.address_informed' => ['nullable', 'string', 'max:500'],
            'dados.destination_property' => ['nullable', 'string', 'max:160', 'required_unless:dados.access_type,turista'],
            'dados.destination_label' => ['required', 'string', 'max:160'],
            'dados.responsible_name' => ['nullable', 'string', 'max:120', 'required_if:dados.access_type,visitante', 'prohibited_unless:dados.access_type,visitante'],
            'dados.visit_date' => ['nullable', 'date_format:Y-m-d'],
            'dados.vehicle_plate' => ['nullable', 'regex:/^[A-Z]{3}\d[A-Z0-9]\d{2}$/'],
            'dados.vehicle_model' => ['nullable', 'string', 'max:100'],
            'dados.vehicle_color' => ['nullable', 'string', 'max:40'],
            'files' => ['required', 'array', 'min:2', 'max:3'],
            'files.*.category' => ['required', Rule::in(['documento', 'documento_verso', 'selfie']), 'distinct'],
            'files.*.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:8388608'],
        ])->validate();

        // Data prevista: do dia do envio até 30 dias depois, no fuso do condomínio.
        $submitted = Carbon::parse($data['submitted_at']);
        $visita = $this->dataDaVisita($data['dados']['visit_date'] ?? null, $submitted);
        unset($data['dados']['visit_date']);

        $expected = $data['dados']['document_type'] === 'rg_cin' ? ['documento', 'documento_verso', 'selfie'] : ['documento', 'selfie'];
        $actual = array_column($data['files'], 'category');
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new FalhaImportacao('arquivos_incompletos');
        }

        $link = DB::table('pre_cadastro_nuvem_recebimentos')->where('implantacao_id', $implantacao->id)->where('cloud_id', $data['id'])->first();
        if ($link !== null) {
            return PreRegistration::query()->findOrFail($link->pre_registration_id);
        }

        $uploads = [];
        $temporary = [];
        $stored = [];
        try {
            foreach ($data['files'] as $file) {
                $response = $this->http()->get($this->url.'/api/sync', ['action' => 'file', 'id' => $data['id'], 'category' => $file['category']]);
                $bytes = $response->body();
                if (! $response->successful() || strlen($bytes) !== $file['size'] || ! hash_equals($file['sha256'], hash('sha256', $bytes))) {
                    throw new FalhaImportacao('arquivo_integridade');
                }
                $dimensions = @getimagesizefromstring($bytes);
                if ($dimensions === false || $dimensions[0] < 320 || $dimensions[1] < 320 || $dimensions[2] !== IMAGETYPE_JPEG) {
                    throw new FalhaImportacao('imagem_invalida');
                }
                $path = tempnam(sys_get_temp_dir(), 'sdv-portal-');
                if ($path === false) {
                    throw new RuntimeException('Não foi possível preparar o arquivo.');
                }
                $temporary[] = $path;
                if (file_put_contents($path, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Não foi possível preparar o arquivo.');
                }
                $uploads[$file['category']] = new UploadedFile($path, $file['category'].'.jpg', 'image/jpeg', null, true);
            }

            return DB::transaction(function () use ($data, $uploads, $implantacao, $submitted, $visita, &$stored): PreRegistration {
                $record = PreRegistration::query()->create([
                    ...$data['dados'],
                    'protocol' => $data['protocol'], 'status' => 'aguardando', 'version' => 1,
                    'period_start' => $visita, 'period_end' => $visita->copy()->addDay(),
                    'submitted_at' => $submitted,
                    'document_status' => 'Arquivo protegido disponível; leitura automática não realizada — conferência manual obrigatória',
                    'selfie_status' => 'Arquivo protegido disponível para conferência',
                    'alert' => 'Recebido do portal público. Conferência manual obrigatória.',
                ]);
                $stored = $this->files->storePreRegistrationFiles($record, $uploads, 'portal_publico_nuvem');
                DB::table('pre_cadastro_nuvem_recebimentos')->insert([
                    'id' => (string) Str::uuid(), 'implantacao_id' => $implantacao->id,
                    'cloud_id' => $data['id'], 'pre_registration_id' => $record->id,
                    'acked_version' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->audit->record(action: 'importou_pre_cadastro_nuvem', module: 'pre-cadastro', entityType: 'pre_registrations', entityId: $record->id,
                    classification: 'restrita', metadata: ['origem' => 'portal_publico_nuvem']);

                return $record;
            });
        } catch (\Throwable $error) {
            foreach ($stored as $file) {
                Storage::disk(PrivateFileService::DISK)->delete($file->object_key);
            }
            throw $error;
        } finally {
            foreach ($temporary as $path) {
                @unlink($path);
            }
        }
    }

    /** Envio antigo, sem data informada, mantém as 24 h a partir do envio. */
    private function dataDaVisita(?string $dia, Carbon $enviado): Carbon
    {
        if ($dia === null) {
            return $enviado->copy();
        }
        $fuso = (string) config('app.timezone');
        $visita = Carbon::createFromFormat('!Y-m-d', $dia, $fuso);
        $primeiro = $enviado->copy()->setTimezone($fuso)->startOfDay();
        if ($visita->lt($primeiro) || $visita->gt($primeiro->copy()->addDays(30))) {
            throw new FalhaImportacao('dados_invalidos', ['dados.visit_date']);
        }

        return $visita;
    }

    private function publicarCatalogo(Implantacao $implantacao): void
    {
        $itens = $this->catalogo->paraPortal();
        $hash = hash('sha256', json_encode($itens, JSON_THROW_ON_ERROR));
        $chave = 'pre-cadastro-nuvem:catalogo:'.$implantacao->id;
        if (Cache::get($chave) === $hash) {
            return;
        }
        $response = $this->http()->post($this->url.'/api/sync', ['action' => 'catalogo', 'itens' => $itens]);
        if (! $response->successful()) {
            throw new SincronizacaoIndisponivel('Nuvem indisponível ou portal desatualizado; os registros locais foram preservados.');
        }
        // Republica ao menos a cada hora, mesmo sem mudança local.
        Cache::put($chave, $hash, 3600);
    }

    /** Guarda só o código e os nomes dos campos; avisa a nuvem para esperar ou pedir conferência. */
    private function registrarFalha(Implantacao $implantacao, mixed $item, \Throwable $error): ?string
    {
        $cloudId = is_array($item) && is_string($item['id'] ?? null) && Str::isUuid($item['id']) ? $item['id'] : null;
        if ($cloudId === null) {
            return null;
        }
        [$codigo, $campos] = match (true) {
            $error instanceof FalhaImportacao => [$error->codigo, $error->campos],
            $error instanceof ValidationException => ['dados_invalidos', array_keys($error->errors())],
            default => ['erro_interno', []],
        };
        $protocolo = is_string($item['protocol'] ?? null) && preg_match('/^PRE-SRA-[A-F0-9]{32}$/', $item['protocol']) ? $item['protocol'] : null;

        $situacao = 'retentando';
        $tentativas = null;
        try {
            $response = $this->http()->post($this->url.'/api/sync', ['action' => 'falha', 'id' => $cloudId, 'code' => $codigo]);
            if ($response->successful()) {
                $situacao = $response->json('state') === 'conferencia' ? 'conferencia' : 'retentando';
                $tentativas = is_int($response->json('attempts')) ? $response->json('attempts') : null;
            }
        } catch (\Throwable) {
            // A nuvem volta a entregar a solicitação no próximo ciclo.
        }

        $existente = DB::table('pre_cadastro_nuvem_falhas')->where('implantacao_id', $implantacao->id)->where('cloud_id', $cloudId)->first();
        $valores = [
            'protocol' => $protocolo, 'codigo' => $codigo, 'campos' => json_encode(array_values(array_slice($campos, 0, 20))),
            'tentativas' => $tentativas ?? (($existente->tentativas ?? 0) + 1), 'situacao' => $situacao,
            'ultima_falha_em' => now(), 'resolvida_em' => null, 'updated_at' => now(),
        ];
        if ($existente === null) {
            DB::table('pre_cadastro_nuvem_falhas')->insert([...$valores, 'id' => (string) Str::uuid(),
                'implantacao_id' => $implantacao->id, 'cloud_id' => $cloudId, 'created_at' => now()]);
        } else {
            DB::table('pre_cadastro_nuvem_falhas')->where('id', $existente->id)->update($valores);
        }
        if ($situacao === 'conferencia' && ($existente->situacao ?? null) !== 'conferencia') {
            $this->audit->record(action: 'sinalizou_pre_cadastro_nuvem_para_conferencia', module: 'pre-cadastro', entityType: 'pre_cadastro_nuvem_falhas',
                entityId: $cloudId, classification: 'restrita', metadata: ['codigo' => $codigo]);
        }

        return $situacao;
    }

    private function confirmar(string $cloudId, PreRegistration $record): void
    {
        $response = $this->http()->post($this->url.'/api/sync', [
            'action' => 'ack', 'id' => $cloudId, 'version' => $record->version, 'status' => $record->status,
        ]);
        if (! $response->successful() || $response->json('acknowledged') !== true) {
            throw new RuntimeException('Confirmação pendente.');
        }
        DB::table('pre_cadastro_nuvem_recebimentos')->where('implantacao_id', $record->implantacao_id)->where('cloud_id', $cloudId)
            ->update(['acked_version' => $record->version, 'updated_at' => now()]);
    }
}
