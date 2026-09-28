<?php

namespace App\Http\Controllers;

use App\Integracoes\Aplicacao\CapturaImagemEquipamento;
use App\Models\Equipamento;
use Illuminate\Http\Response;

/**
 * Entrega ao navegador a última imagem capturada pelo backend. O navegador
 * nunca acessa o terminal: a rota é do SDV, autenticada, sem credencial na
 * URL, restrita à implantação atual (escopo do model) e sem cache.
 */
class EquipamentoImagemController extends Controller
{
    public function __invoke(Equipamento $equipamento, CapturaImagemEquipamento $capturas): Response
    {
        $imagem = $capturas->ultima($equipamento) ?? abort(404);

        return response($imagem['conteudo'], 200, [
            'Content-Type' => $imagem['tipo_mime'],
            'Content-Disposition' => 'inline; filename="captura.'.($imagem['tipo_mime'] === 'image/png' ? 'png' : 'jpg').'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
