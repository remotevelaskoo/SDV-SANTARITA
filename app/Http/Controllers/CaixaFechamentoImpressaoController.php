<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CaixasOperador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Versão limpa do fechamento para impressão, sem menu lateral (AJ-008,
 * SDV-UXR-014 §5.6). Cada impressão é auditada; nada é alterado.
 */
class CaixaFechamentoImpressaoController extends Controller
{
    public function __invoke(Request $request, string $turno, CaixasOperador $caixas): View
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 403);

        $fechamento = $caixas->consultar($usuario, $turno, 'imprimiu_fechamento_caixa');
        abort_unless($fechamento->fechado(), 404);
        $fechamento->loadMissing('implantacao');

        return view('caixa.fechamento-impressao', [
            'turno' => $fechamento,
            'pagantes' => $caixas->pagantes($fechamento),
            'quantidades' => $fechamento->quantidades(),
        ]);
    }
}
