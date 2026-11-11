<?php

namespace App\Http\Controllers;

use App\Services\PreCadastro\LinkPublico;
use Illuminate\Contracts\View\View;

/** Página para imprimir/compartilhar o link do pré-cadastro com QR code. */
class PreCadastroDivulgacaoController extends Controller
{
    public function __invoke(LinkPublico $link): View
    {
        return view('pre-cadastro.divulgacao', [
            'url' => $link->url(),
            'qr' => $link->qrSvg(),
        ]);
    }
}
