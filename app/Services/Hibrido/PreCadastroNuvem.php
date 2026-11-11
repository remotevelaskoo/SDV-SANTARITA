<?php

namespace App\Services\Hibrido;

use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\AuditService;
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
use RuntimeException;

/** Ponte de saída: nunca abre uma porta da rede local para a internet. */
class PreCadastroNuvem
{
    private string $url;

    private string $token;

    public function __construct(private PrivateFileService $files, private AuditService $audit) {}

    /** @return array{importados:int,confirmados:int,falhas:int} */
    public function sincronizar(): array
    {
        if (! config('pre_cadastro_nuvem.enabled')) {
            throw new RuntimeException('Sincronização do pré-cadastro desativada.');
        }

        $this->url = rtrim((string) config('pre_cadastro_nuvem.url'), '/');
        $this->token = (string) config('pre_cadastro_nuvem.token');
        $parsed = parse_url($this->url);
        if (! is_array($parsed) || ($parsed['scheme'] ?? '') !== 'https' || empty($parsed['host'])
            || (isset($parsed['user']) || isset($parsed['pass'])) || isset($parsed['query']) || isset($parsed['fragment'])
            || ! in_array($parsed['path'] ?? '', ['', '/'], true) || strlen($this->token) < 32) {
            throw new RuntimeException('Configure um endereço HTTPS e a credencial exclusiva do nó.');
        }

        $implantacao = Implantacao::query()->whereKey(config('pre_cadastro_nuvem.implantacao_id'))->where('status', 'ativa')->firstOrFail();
        $lock = Cache::lock('pre-cadastro-nuvem:'.$implantacao->id, 3600);
        if (! $lock->get()) {
            throw new RuntimeException('Outra sincronização está em andamento.');
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

    /** @return array{importados:int,confirmados:int,falhas:int} */
    private function executar(Implantacao $implantacao): array
    {
        $totais = ['importados' => 0, 'confirmados' => 0, 'falhas' => 0];
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

        $response = $this->http()->get($this->url.'/api/sync', ['action' => 'pull']);
        if (! $response->successful()) {
            throw new RuntimeException('Nuvem indisponível; os dados locais e as confirmações pendentes foram preservados.');
        }
        $records = $response->json('records');
        if (! is_array($records) || count($records) > 10) {
            throw new RuntimeException('Resposta inválida do portal.');
        }

        foreach ($records as $item) {
            try {
                $record = $this->importar($implantacao, $item);
                $totais['importados']++;
                $this->confirmar($item['id'], $record);
                $totais['confirmados']++;
            } catch (\Throwable) {
                // Não registrar payload, documento, foto, token nem mensagem de terceiros.
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
            'submitted_at' => ['required', 'date', 'before_or_equal:now'],
            'dados' => ['required', 'array:name,document,document_type,birth_date,phone,email,access_type,address_informed,destination_property,destination_label,responsible_name,vehicle_plate,vehicle_model,vehicle_color'],
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
            'dados.vehicle_plate' => ['nullable', 'regex:/^[A-Z]{3}\d[A-Z0-9]\d{2}$/'],
            'dados.vehicle_model' => ['nullable', 'string', 'max:100'],
            'dados.vehicle_color' => ['nullable', 'string', 'max:40'],
            'files' => ['required', 'array', 'min:2', 'max:3'],
            'files.*.category' => ['required', Rule::in(['documento', 'documento_verso', 'selfie']), 'distinct'],
            'files.*.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:8388608'],
        ])->validate();

        $expected = $data['dados']['document_type'] === 'rg_cin' ? ['documento', 'documento_verso', 'selfie'] : ['documento', 'selfie'];
        $actual = array_column($data['files'], 'category');
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new RuntimeException('Conjunto de arquivos incompleto.');
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
                    throw new RuntimeException('Não foi possível conferir a integridade do arquivo.');
                }
                $dimensions = @getimagesizefromstring($bytes);
                if ($dimensions === false || $dimensions[0] < 320 || $dimensions[1] < 320 || $dimensions[2] !== IMAGETYPE_JPEG) {
                    throw new RuntimeException('Imagem inválida.');
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

            return DB::transaction(function () use ($data, $uploads, $implantacao, &$stored): PreRegistration {
                $submitted = Carbon::parse($data['submitted_at']);
                $record = PreRegistration::query()->create([
                    ...$data['dados'],
                    'protocol' => $data['protocol'], 'status' => 'aguardando', 'version' => 1,
                    'period_start' => $submitted, 'period_end' => $submitted->copy()->addDay(),
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
