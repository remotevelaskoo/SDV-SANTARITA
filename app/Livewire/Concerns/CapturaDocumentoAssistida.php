<?php

namespace App\Livewire\Concerns;

use App\Documentos\Aplicacao\CadastroAssistidoDocumento;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Captura assistida do documento no Cadastro rápido (SDV-OPE-018 §5).
 *
 * A imagem chega como upload temporário (câmera do posto ou arquivo de
 * contingência), é analisada em memória e o arquivo temporário é apagado
 * logo em seguida: nada é persistido sem política de retenção aprovada.
 * O OCR só sugere valores; campos de baixa confiança ficam "Requer
 * conferência" até o operador confirmar ou corrigir.
 */
trait CapturaDocumentoAssistida
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $capturaFrente = null;

    public ?TemporaryUploadedFile $capturaVerso = null;

    /** 'camera' ou 'arquivo' (contingência). */
    public string $capturaOrigem = 'camera';

    /** @var array<string, mixed>|null resultado da última análise, sem imagem */
    public ?array $analiseDocumento = null;

    /** @var list<string> campos do formulário que exigem conferência humana */
    public array $camposConferencia = [];

    public string $quickBirthDate = '';

    /** Campos do OCR => campos do formulário do cadastro rápido. */
    private const MAPA_CAMPOS = ['nome' => 'quickName', 'cpf' => 'quickDocument', 'data_nascimento' => 'quickBirthDate'];

    public function analisarDocumento(CadastroAssistidoDocumento $assistente): void
    {
        if (! $this->podeCapturarDocumento()) {
            $this->descartarArquivosTemporarios();
            $this->addError('capturaFrente', 'Você não tem permissão para capturar documentos.');

            return;
        }

        $this->validate([
            'capturaFrente' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.(int) config('documentos.captura.tamanho_maximo_kb')],
            'capturaVerso' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:'.(int) config('documentos.captura.tamanho_maximo_kb')],
        ], [
            'capturaFrente.required' => 'Capture a frente do documento ou envie um arquivo.',
        ]);

        $frente = (string) file_get_contents($this->capturaFrente->getRealPath());
        $verso = $this->capturaVerso ? (string) file_get_contents($this->capturaVerso->getRealPath()) : null;
        $this->descartarArquivosTemporarios();

        try {
            $analise = $assistente->analisar($this->usuarioCaptura(), $frente, $verso, $this->capturaOrigem);
        } catch (RegraIntegracaoViolada $e) {
            $this->addError('capturaFrente', $e->getMessage());

            return;
        } finally {
            unset($frente, $verso);
        }

        $this->analiseDocumento = [
            'status' => $analise['status'],
            'problemas' => $analise['problemas'],
            'mensagem' => $analise['mensagem'],
            'duplicidade' => $analise['duplicidade'],
            'extras' => array_intersect_key(
                array_map(fn ($c) => ['valor' => $c['valor'], 'conferir' => $c['conferir']], $analise['campos']),
                array_flip(['numero_documento', 'validade']),
            ),
        ];

        if ($analise['status'] !== 'sugestao') {
            // Qualidade ruim ou documento não identificado: nada é preenchido.
            return;
        }

        $this->camposConferencia = [];
        foreach (self::MAPA_CAMPOS as $campoOcr => $propriedade) {
            $valor = $analise['campos'][$campoOcr]['valor'] ?? null;
            if ($valor === null) {
                continue;
            }
            $this->{$propriedade} = $valor;
            if ($analise['campos'][$campoOcr]['conferir']) {
                $this->camposConferencia[] = $propriedade;
            }
        }

        $this->checkQuickDocument();
    }

    public function confirmarCampo(string $propriedade): void
    {
        $this->camposConferencia = array_values(array_diff($this->camposConferencia, [$propriedade]));
    }

    /** Correção manual de um campo conta como conferência humana. */
    public function updatedQuickName(): void
    {
        $this->confirmarCampo('quickName');
    }

    public function updatedQuickDocument(): void
    {
        $this->confirmarCampo('quickDocument');
    }

    public function updatedQuickBirthDate(): void
    {
        $this->confirmarCampo('quickBirthDate');
    }

    public function descartarCaptura(string $lado = 'frente'): void
    {
        $propriedade = $lado === 'verso' ? 'capturaVerso' : 'capturaFrente';
        $this->{$propriedade}?->delete();
        $this->{$propriedade} = null;
    }

    public function digitarManualmente(): void
    {
        $this->descartarArquivosTemporarios();
        $this->analiseDocumento = null;
        $this->camposConferencia = [];
    }

    public function podeCapturarDocumento(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User && $usuario->hasPermission(CadastroAssistidoDocumento::PERMISSAO);
    }

    /** @return array{implantacao: string, posto: string} chaves das câmeras locais deste posto */
    public function contextoCameras(): array
    {
        $usuario = $this->usuarioCaptura();
        $turno = rescue(fn () => app(TurnosOperacionais::class)->turnoAtivo($usuario), null, false);

        return ['implantacao' => (string) ($turno?->implantacao_id ?? session('implantacao_atual_id', '')), 'posto' => (string) ($turno?->posto_id ?? 'sem-posto')];
    }

    private function descartarArquivosTemporarios(): void
    {
        $this->descartarCaptura('frente');
        $this->descartarCaptura('verso');
    }

    private function usuarioCaptura(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
