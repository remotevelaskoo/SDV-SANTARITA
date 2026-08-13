<?php

namespace App\Http\Controllers;

use App\Models\Arquivo;
use App\Models\ArquivoAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArquivoController extends Controller
{
    // Servir binário via resposta em stream não é responsabilidade de um
    // componente Livewire — por isso este é o primeiro controller do
    // projeto. A vinculação de rota já aplica o ImplantacaoScope do model
    // (arquivo de outra implantação dá 404, mesmo comportamento de
    // qualquer outra consulta a uma entidade com BelongsToImplantacao).
    public function __invoke(Request $request, Arquivo $arquivo): StreamedResponse
    {
        ArquivoAcesso::query()->create([
            'arquivo_id' => $arquivo->id,
            'ator_id' => Auth::id(),
            'contexto' => $request->query('contexto', 'desconhecido'),
            'resultado' => 'sucesso',
            'occurred_at' => now(),
        ]);

        return Storage::disk($arquivo->disco)->response($arquivo->caminho, null, [
            'Content-Disposition' => 'inline',
        ]);
    }
}
