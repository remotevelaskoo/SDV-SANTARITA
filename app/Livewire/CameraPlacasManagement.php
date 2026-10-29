<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CamerasPlaca;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\CapturaPlaca;
use App\Models\Equipamento;
use App\Models\PontoAcesso;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Câmeras IP de placas (AJ-006, fatia 5): cadastro, credencial por
 * referência, vínculo ao ponto e captura de teste. Endereço completo e
 * referência do segredo nunca voltam para a tela depois de gravados.
 */
class CameraPlacasManagement extends Component
{
    /** @var array{nome: string, fabricante: string, modelo: string, endereco_rede: string, porta_rede: string, esquema: string, caminho_snapshot: string, adaptador: string, timeout_segundos: string} */
    public array $form = [];

    /** @var array<string, string> câmera => ponto escolhido */
    public array $pontoEscolhido = [];

    /** @var array<string, array{referencia: string, usuario: string}> */
    public array $credencial = [];

    public string $motivo = '';

    public ?string $ultimaCapturaId = null;

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public const ADAPTADORES = [
        'snapshot-http' => 'Snapshot HTTP/HTTPS da câmera',
        'gateway-video' => 'Gateway de vídeo (RTSP/ONVIF convertido em snapshot)',
        'simulador-camera' => 'SIMULADOR (imagem sintética, sem câmera)',
    ];

    public function mount(): void
    {
        $this->autorizar();
        $this->limparFormulario();
    }

    public function cadastrar(CamerasPlaca $cameras): void
    {
        $ator = $this->autorizar();
        $this->validate([
            'form.nome' => ['required', 'string', 'max:120'],
            'form.adaptador' => ['required', 'string'],
            'form.porta_rede' => ['nullable', 'integer', 'between:1,65535'],
            'form.timeout_segundos' => ['required', 'integer', 'between:1,30'],
        ], attributes: ['form.nome' => 'nome', 'form.porta_rede' => 'porta', 'form.timeout_segundos' => 'tempo limite']);

        $this->executar(fn () => $cameras->cadastrar([
            ...$this->form,
            'porta_rede' => $this->form['porta_rede'] === '' ? null : (int) $this->form['porta_rede'],
            'timeout_segundos' => (int) $this->form['timeout_segundos'],
        ], $ator), 'Câmera cadastrada', 'Defina a credencial (se houver) e vincule a câmera a um ponto para liberar o uso na validação.');

        if ($this->feedback['variant'] === 'success') {
            $this->limparFormulario();
        }
    }

    public function definirCredencial(string $cameraId, CamerasPlaca $cameras): void
    {
        $ator = $this->autorizar();
        $dados = $this->credencial[$cameraId] ?? ['referencia' => '', 'usuario' => ''];

        $this->executar(fn () => $cameras->definirCredencial($this->camera($cameraId), trim($dados['referencia']), trim($dados['usuario']) ?: null, $ator), 'Credencial registrada', 'Só a referência foi gravada. A senha continua fora do SDV.');
        unset($this->credencial[$cameraId]);
    }

    public function vincular(string $cameraId, CamerasPlaca $cameras): void
    {
        $ator = $this->autorizar();
        $ponto = PontoAcesso::query()->find($this->pontoEscolhido[$cameraId] ?? '');
        if ($ponto === null) {
            $this->feedback = ['variant' => 'danger', 'title' => 'Escolha o ponto', 'message' => 'Selecione o ponto de acesso que esta câmera enxerga.'];

            return;
        }

        $this->executar(fn () => $cameras->vincularAoPonto($this->camera($cameraId), $ponto, $ator), 'Câmera vinculada ao ponto', 'A câmera já aparece na validação dos postos que atendem este ponto.');
    }

    public function testarCaptura(string $cameraId, CamerasPlaca $cameras): void
    {
        $ator = $this->autorizar();

        try {
            $resultado = $cameras->capturar($cameraId, $ator);
        } catch (RegraIntegracaoViolada $e) {
            $this->feedback = ['variant' => 'danger', 'title' => 'Captura não realizada', 'message' => $e->getMessage()];

            return;
        }

        if ($resultado['ok']) {
            $this->ultimaCapturaId = $resultado['captura']->id;
            $this->feedback = ['variant' => 'success', 'title' => 'Imagem capturada', 'message' => $resultado['captura']->simulada() ? 'Imagem SINTÉTICA do simulador; não houve conexão com câmera.' : 'A câmera respondeu com uma imagem válida.'];
        } else {
            $this->feedback = ['variant' => 'danger', 'title' => 'Captura não realizada', 'message' => $resultado['mensagem']];
        }
    }

    public function inativar(string $cameraId, CamerasPlaca $cameras): void
    {
        $ator = $this->autorizar();
        $this->executar(fn () => $cameras->inativar($this->camera($cameraId), $this->motivo, $ator), 'Câmera inativada', 'As capturas já feitas continuam guardadas como evidência.');
        $this->motivo = '';
    }

    public function render(CamerasPlaca $cameras): View
    {
        $ultima = $this->ultimaCapturaId ? CapturaPlaca::query()->with(['equipamento', 'pontoAcesso'])->find($this->ultimaCapturaId) : null;

        return view('livewire.camera-placas-management', [
            'cameras' => Equipamento::query()->camerasPlaca()->with(['vinculoCameraVigente.pontoAcesso', 'credenciaisTecnicas'])->orderBy('nome')->get(),
            'pontos' => PontoAcesso::query()->where('status', '!=', 'inativo')->orderBy('nome')->get(),
            'adaptadores' => array_intersect_key(self::ADAPTADORES, array_flip($cameras->adaptadoresPermitidos())),
            'ultimaCaptura' => $ultima,
            'podeVerImagem' => (bool) Auth::user()?->hasPermission('arquivos.sensiveis.visualizar'),
        ])->layout('components.layouts.app', [
            'title' => 'Câmeras de placas',
            'heading' => 'Câmeras de placas',
            'headingDescription' => 'Imagem estática sob demanda para conferir a placa na validação',
        ]);
    }

    private function camera(string $id): Equipamento
    {
        return Equipamento::query()->camerasPlaca()->findOrFail($id);
    }

    private function limparFormulario(): void
    {
        $this->form = [
            'nome' => '', 'fabricante' => '', 'modelo' => '', 'endereco_rede' => '', 'porta_rede' => '',
            'esquema' => 'http', 'caminho_snapshot' => '', 'adaptador' => 'snapshot-http', 'timeout_segundos' => '5',
        ];
    }

    private function executar(callable $acao, string $titulo, string $mensagem): void
    {
        try {
            $acao();
            $this->feedback = ['variant' => 'success', 'title' => $titulo, 'message' => $mensagem];
            $this->resetErrorBag();
        } catch (RegraIntegracaoViolada $e) {
            $this->feedback = ['variant' => 'danger', 'title' => 'Não foi possível concluir', 'message' => $e->getMessage()];
        }
    }

    private function autorizar(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User && $usuario->hasPermission('integracoes.gerenciar'), 403);

        return $usuario;
    }
}
