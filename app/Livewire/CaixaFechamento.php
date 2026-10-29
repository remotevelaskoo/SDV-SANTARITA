<?php

namespace App\Livewire;

use App\Models\CaixaTurno;
use App\Models\User;
use App\Services\CaixasOperador;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resumo de um fechamento de caixa (AJ-008, SDV-UXR-014 §§5.4 e 5.6).
 * Somente leitura: nada aqui altera o turno, as contribuições ou as
 * movimentações. Porteiro/Caixa vê os próprios; a visão consolidada vê todos.
 */
class CaixaFechamento extends Component
{
    public string $turnoId;

    public function mount(string $turno, CaixasOperador $caixas): void
    {
        // A consulta de fechamento de outro operador é auditada uma vez, na abertura.
        $this->turnoId = $caixas->consultar($this->usuario(), $turno)->id;
    }

    public function exportarCsv(CaixasOperador $caixas): StreamedResponse
    {
        $turno = $caixas->consultar($this->usuario(), $this->turnoId, 'exportou_fechamento_caixa');
        abort_unless($turno->fechado(), 404);
        $linhas = $caixas->pagantes($turno);

        return response()->streamDownload(function () use ($linhas): void {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['Data e hora', 'Pessoa', 'Documento', 'Protocolo', 'Ticket', 'Valor', 'Forma de pagamento', 'Operador', 'Ponto de acesso', 'Ticket repetido', 'Ticket anterior'], ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, [
                    $linha['horario']?->format('d/m/Y H:i'),
                    $linha['pessoa'],
                    $linha['documento'],
                    $linha['protocolo'],
                    // Prefixo evita que planilhas removam zeros à esquerda ou interpretem fórmula.
                    $linha['ticket'] !== null ? "'".$linha['ticket'] : '',
                    number_format($linha['valor'], 2, ',', ''),
                    $linha['forma'],
                    $linha['operador'],
                    $linha['ponto'],
                    $linha['ticket_repetido'] ? 'Sim' : 'Não',
                    $linha['ticket_anterior'] !== null ? "'".$linha['ticket_anterior'] : '',
                ], ';');
            }
            fclose($saida);
        }, 'fechamento-'.($turno->numero_fechamento ?? $turno->id).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(CaixasOperador $caixas): View
    {
        $turno = CaixaTurno::query()->with(['operator', 'posto', 'closedBy', 'implantacao'])->findOrFail($this->turnoId);
        abort_unless($caixas->podeConsultar($this->usuario(), $turno), 403);
        abort_unless($turno->fechado(), 404);

        return view('livewire.caixa-fechamento', [
            'turno' => $turno,
            'pagantes' => $caixas->pagantes($turno),
            'quantidades' => $turno->quantidades(),
        ])->layout('components.layouts.app', [
            'title' => 'Fechamento de caixa',
            'heading' => 'Fechamento de caixa',
            'headingDescription' => 'Resumo conciliado do turno, pessoas pagantes e diferença',
        ]);
    }

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
