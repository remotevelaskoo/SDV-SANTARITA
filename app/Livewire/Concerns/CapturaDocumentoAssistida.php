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
 *
 * AJ-010: a leitura começa sozinha quando a captura confirmada chega (frente
 * na CNH; frente e verso no RG/CIN, cujo verso traz CPF e nascimento). Uma
 * nova leitura não apaga o que o operador já corrigiu: o valor dele fica e
 * a tela avisa a divergência.
 */
trait CapturaDocumentoAssistida
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $capturaFrente = null;

    public ?TemporaryUploadedFile $capturaVerso = null;

    /** 'camera' ou 'arquivo' (contingência). */
    public string $capturaOrigem = 'camera';

    /** 'cnh' ou 'rg_cin': orienta o leitor sobre o número do documento. */
    public string $capturaTipoDocumento = 'cnh';

    /** @var array<string, mixed>|null resultado da última análise, sem imagem */
    public ?array $analiseDocumento = null;

    /** @var list<string> campos do formulário que exigem conferência humana */
    public array $camposConferencia = [];

    public string $quickBirthDate = '';

    /** Número do RG/CIN ou da CNH, guardado com o tipo correto (nunca vira CPF). */
    public string $quickDocNumero = '';

    /** @var list<string> campos que o operador digitou ou corrigiu */
    public array $camposEditados = [];

    /** Aviso de valores lidos que não substituíram a correção do operador. */
    public ?string $avisoLeitura = null;

    /** Campos do OCR => campos do formulário do cadastro rápido. */
    private const MAPA_CAMPOS = ['nome' => 'quickName', 'cpf' => 'quickDocument', 'data_nascimento' => 'quickBirthDate', 'numero_documento' => 'quickDocNumero'];

    private const ROTULOS_CAMPOS = ['quickName' => 'nome', 'quickDocument' => 'CPF', 'quickBirthDate' => 'nascimento', 'quickDocNumero' => 'número do documento'];

    /** RG/CIN: CPF e nascimento ficam no verso (mesma regra do formulário público). */
    public function versoObrigatorio(): bool
    {
        return $this->capturaTipoDocumento === 'rg_cin';
    }

    /** Leitura automática ao receber a captura confirmada (sem botão "analisar"). */
    public function updatedCapturaFrente(): void
    {
        if ($this->capturaForaDaEtapaDocumento()) {
            return;
        }
        $this->resetErrorBag(['capturaFrente', 'capturaVerso']);
        if ($this->capturaFrente !== null && (! $this->versoObrigatorio() || $this->capturaVerso !== null)) {
            $this->analisarDocumento(app(CadastroAssistidoDocumento::class));
        }
    }

    public function updatedCapturaVerso(): void
    {
        if ($this->capturaForaDaEtapaDocumento()) {
            return;
        }
        $this->resetErrorBag(['capturaFrente', 'capturaVerso']);
        if ($this->capturaFrente !== null && $this->capturaVerso !== null) {
            $this->analisarDocumento(app(CadastroAssistidoDocumento::class));
        }
    }

    public function updatedCapturaTipoDocumento(): void
    {
        $this->capturaTipoDocumento = in_array($this->capturaTipoDocumento, ['cnh', 'rg_cin'], true) ? $this->capturaTipoDocumento : 'cnh';
    }

    public function analisarDocumento(CadastroAssistidoDocumento $assistente): void
    {
        if ($this->capturaForaDaEtapaDocumento()) {
            return;
        }
        if (! $this->podeCapturarDocumento()) {
            $this->descartarArquivosTemporarios();
            $this->addError('capturaFrente', 'Você não tem permissão para capturar documentos.');

            return;
        }

        $this->validate([
            'capturaFrente' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.(int) config('documentos.captura.tamanho_maximo_kb')],
            'capturaVerso' => [$this->versoObrigatorio() ? 'required' : 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:'.(int) config('documentos.captura.tamanho_maximo_kb')],
        ], [
            'capturaFrente.required' => 'Capture a frente do documento ou envie um arquivo.',
            'capturaVerso.required' => 'Capture também o verso do RG/CIN: CPF e nascimento ficam no verso.',
        ]);

        $frente = (string) file_get_contents($this->capturaFrente->getRealPath());
        $verso = $this->capturaVerso ? (string) file_get_contents($this->capturaVerso->getRealPath()) : null;
        $this->descartarArquivosTemporarios();

        try {
            $analise = $assistente->analisar($this->usuarioCaptura(), $frente, $verso, $this->capturaOrigem, $this->capturaTipoDocumento);
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
                array_flip(['validade']),
            ),
        ];

        if ($analise['status'] !== 'sugestao') {
            // Qualidade ruim ou documento não identificado: nada é preenchido.
            return;
        }

        $this->camposConferencia = [];
        $mantidos = [];
        foreach (self::MAPA_CAMPOS as $campoOcr => $propriedade) {
            $valor = $analise['campos'][$campoOcr]['valor'] ?? null;
            if ($valor === null) {
                continue;
            }
            if (in_array($propriedade, $this->camposEditados, true) && $this->{$propriedade} !== '' && $this->{$propriedade} !== $valor) {
                // Correção do operador vence a nova leitura; avisar em vez de apagar.
                $mantidos[] = self::ROTULOS_CAMPOS[$propriedade];

                continue;
            }
            $this->{$propriedade} = $valor;
            if ($analise['campos'][$campoOcr]['conferir']) {
                $this->camposConferencia[] = $propriedade;
            }
        }
        $this->avisoLeitura = $mantidos === [] ? null
            : 'A nova leitura trouxe valores diferentes para '.implode(', ', $mantidos).'. Mantivemos o que você corrigiu; confira.';

        $this->checkQuickDocument();
        $this->avancarParaConferencia();
    }

    /** Ponto de extensão: a tela avança para a conferência após uma leitura útil. */
    protected function avancarParaConferencia(): void {}

    public function confirmarCampo(string $propriedade): void
    {
        $this->camposConferencia = array_values(array_diff($this->camposConferencia, [$propriedade]));
    }

    /** Correção manual de um campo conta como conferência humana. */
    public function updatedQuickName(): void
    {
        $this->marcarEditado('quickName');
    }

    public function updatedQuickDocument(): void
    {
        $this->marcarEditado('quickDocument');
    }

    public function updatedQuickBirthDate(): void
    {
        $this->marcarEditado('quickBirthDate');
    }

    public function updatedQuickDocNumero(): void
    {
        $this->marcarEditado('quickDocNumero');
    }

    private function marcarEditado(string $propriedade): void
    {
        $this->confirmarCampo($propriedade);
        $this->camposEditados = array_values(array_unique([...$this->camposEditados, $propriedade]));
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
        $this->avisoLeitura = null;
        $this->avancarParaConferencia();
        // Foco no Nome completo; a câmera do documento já foi fechada no navegador.
        $this->dispatch('cadastro-rapido-foco-nome');
    }

    /**
     * Upload ou leitura que chega depois de o operador sair da etapa Documento
     * (por exemplo, ao escolher "Cadastrar manualmente") é descartado e não
     * sobrescreve o que já foi digitado.
     */
    private function capturaForaDaEtapaDocumento(): bool
    {
        // Sem permissão de captura, o caminho normal já recusa com a mensagem própria.
        if (! property_exists($this, 'quickEtapa') || $this->quickEtapa === 'documento' || ! $this->podeCapturarDocumento()) {
            return false;
        }
        $this->descartarArquivosTemporarios();

        return true;
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

    protected function descartarArquivosTemporarios(): void
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
