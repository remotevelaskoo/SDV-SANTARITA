<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Dados\ImagemCapturada;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Integracoes\Infra\RegistroAdaptadores;
use App\Integracoes\Infra\SanitizadorIntegracao;
use App\Models\Equipamento;
use App\Models\IntegracaoOcorrencia;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Atualizar imagem": pede ao terminal uma imagem estática da câmera e a
 * guarda por pouco tempo para exibição na administração.
 *
 * - o navegador nunca fala com o terminal: a imagem é servida por rota
 *   autenticada do SDV, sem credencial na URL;
 * - tipo, assinatura e tamanho são validados antes de guardar;
 * - fica só a captura mais recente, cifrada em cache e com expiração curta
 *   (`integracoes.captura.retencao_segundos`); não há arquivo nem histórico
 *   de imagens até existir política aprovada (ADR-006, ADR-013);
 * - a captura nunca vira credencial biométrica;
 * - auditoria e ocorrência registram metadados, nunca a imagem.
 */
class CapturaImagemEquipamento
{
    public function __construct(
        private RegistroAdaptadores $adaptadores,
        private FabricaContextoEquipamento $contextos,
        private SanitizadorIntegracao $sanitizador,
        private AuditService $audit,
    ) {}

    /**
     * @return array{resultado: string, codigo: ?string, mensagem: ?string, capturada_em: ?string, tamanho_bytes: int, tipo_mime: ?string}
     */
    public function capturar(Equipamento $equipamento, User $ator): array
    {
        if (! $ator->hasPermission('integracoes.gerenciar')) {
            throw new RegraIntegracaoViolada('sem_permissao', 'Você não tem permissão para operar equipamentos.');
        }
        if ($equipamento->status === StatusEquipamento::Inativo) {
            throw new RegraIntegracaoViolada('equipamento_inativo', 'Equipamento inativo.');
        }
        if (! $this->adaptadores->permitido($equipamento->adaptador)) {
            throw new RegraIntegracaoViolada('adaptador_indisponivel', 'Adaptador não permitido neste ambiente.');
        }

        $trava = Cache::lock('integracoes:captura:trava:'.$equipamento->id, 30);
        if (! $trava->get()) {
            throw new RegraIntegracaoViolada('captura_em_andamento', 'Já existe uma captura em andamento para este terminal.');
        }

        try {
            $inicio = hrtime(true);
            $imagem = $equipamento->suporta(Capacidade::CapturarImagem)
                ? $this->chamarAdaptador($equipamento)
                : ImagemCapturada::capacidadeAusente('não verificada para este terminal; consulte as capacidades');
            $latencia = (int) ((hrtime(true) - $inicio) / 1_000_000);

            $imagem = $this->validar($imagem);
            $this->registrar($equipamento, $imagem, $latencia);

            return [
                'resultado' => $imagem->resultado->value,
                'codigo' => $imagem->codigo,
                'mensagem' => $this->sanitizador->texto($imagem->mensagem),
                'capturada_em' => $imagem->capturadaEm?->format(DATE_ATOM),
                'tamanho_bytes' => $imagem->tamanhoBytes(),
                'tipo_mime' => $imagem->tipoMime,
            ];
        } finally {
            $trava->release();
        }
    }

    /** @return array{conteudo: string, tipo_mime: string, capturada_em: string}|null */
    public function ultima(Equipamento $equipamento): ?array
    {
        $guardado = Cache::get($this->chave($equipamento));
        if (! is_string($guardado)) {
            return null;
        }

        try {
            $dados = json_decode(Crypt::decryptString($guardado), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            Cache::forget($this->chave($equipamento));

            return null;
        }

        $conteudo = base64_decode((string) ($dados['conteudo'] ?? ''), true);

        return $conteudo === false || $conteudo === '' ? null : [
            'conteudo' => $conteudo,
            'tipo_mime' => (string) $dados['tipo_mime'],
            'capturada_em' => (string) $dados['capturada_em'],
        ];
    }

    private function chamarAdaptador(Equipamento $equipamento): ImagemCapturada
    {
        $porta = $this->adaptadores->para($equipamento->adaptador);

        try {
            return $porta->capturarImagem($this->contextos->para($equipamento, (string) Str::uuid7()));
        } catch (Throwable $e) {
            Log::warning('integracao.excecao_adaptador', [
                'equipamento_id' => $equipamento->id,
                'operacao' => 'capturar_imagem',
                'erro' => $this->sanitizador->texto($e->getMessage(), $this->contextos->segredosConhecidos($equipamento)),
            ]);

            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'excecao_adaptador', 'Falha inesperada ao capturar a imagem.');
        }
    }

    private function validar(ImagemCapturada $imagem): ImagemCapturada
    {
        if ($imagem->resultado !== ResultadoEquipamento::Confirmado) {
            return $imagem;
        }

        $maximo = (int) config('integracoes.captura.tamanho_maximo_bytes');

        return match (true) {
            ! in_array($imagem->tipoMime, ['image/jpeg', 'image/png'], true) => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'Tipo de imagem não aceito.'),
            $imagem->tamanhoBytes() === 0 => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_indisponivel', 'O terminal devolveu uma imagem vazia.'),
            $imagem->tamanhoBytes() > $maximo => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_grande_demais', 'A imagem excede o tamanho máximo aceito.'),
            @getimagesizefromstring((string) $imagem->conteudo()) === false => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O conteúdo recebido não é uma imagem válida.'),
            default => $imagem,
        };
    }

    private function registrar(Equipamento $equipamento, ImagemCapturada $imagem, int $latencia): void
    {
        $sucesso = $imagem->resultado === ResultadoEquipamento::Confirmado;
        $mensagem = $this->sanitizador->texto($imagem->mensagem);

        if ($sucesso) {
            Cache::put($this->chave($equipamento), Crypt::encryptString(json_encode([
                'conteudo' => base64_encode((string) $imagem->conteudo()),
                'tipo_mime' => $imagem->tipoMime,
                'capturada_em' => $imagem->capturadaEm?->format(DATE_ATOM),
            ], JSON_THROW_ON_ERROR)), (int) config('integracoes.captura.retencao_segundos'));
        }

        if ($imagem->resultado !== ResultadoEquipamento::CapacidadeAusente) {
            $equipamento->forceFill($sucesso
                ? ['ultima_comunicacao_at' => now()]
                : ['ultima_falha_at' => now(), 'ultimo_erro_sanitizado' => $mensagem])->save();
        }

        IntegracaoOcorrencia::query()->create([
            'equipamento_id' => $equipamento->id,
            'tipo' => 'captura_imagem',
            'resultado' => $imagem->resultado->value,
            'codigo' => $imagem->codigo,
            'mensagem_sanitizada' => $mensagem,
            'latencia_ms' => $latencia,
            'ocorrido_em' => now(),
        ]);

        $this->audit->record(
            action: 'equipamento_imagem_capturada',
            module: 'integracoes',
            entityType: 'equipamentos',
            entityId: $equipamento->id,
            result: $sucesso ? 'sucesso' : 'falha',
            reasonCode: $imagem->codigo,
            metadata: [
                'resultado' => $imagem->resultado->value,
                'tipo_mime' => $imagem->tipoMime,
                'tamanho_bytes' => $imagem->tamanhoBytes(),
                'retencao_segundos' => $sucesso ? (int) config('integracoes.captura.retencao_segundos') : null,
                'latencia_ms' => $latencia,
            ],
        );
    }

    private function chave(Equipamento $equipamento): string
    {
        return 'integracoes:captura:'.$equipamento->implantacao_id.':'.$equipamento->id;
    }
}
