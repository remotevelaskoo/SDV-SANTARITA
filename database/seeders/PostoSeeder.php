<?php

namespace Database\Seeders;

use App\Models\Implantacao;
use App\Models\Posto;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Seeder;

/**
 * Postos operacionais iniciais da implantação Santa Rita (SDV-OPE-018 §2).
 * Idempotente: um posto existente com o mesmo código ou nome só recebe o tipo.
 */
class PostoSeeder extends Seeder
{
    public function run(): void
    {
        $implantacao = Implantacao::query()->where('slug', 'santa-rita')->first();
        if ($implantacao === null) {
            return;
        }

        ImplantacaoContext::executarNo($implantacao, function (): void {
            foreach (Posto::INICIAIS as $dados) {
                $posto = Posto::query()
                    ->where('codigo', $dados['codigo'])
                    ->orWhereRaw('upper(nome) = ?', [mb_strtoupper($dados['nome'])])
                    ->first();

                $posto === null
                    ? Posto::query()->create([...$dados, 'status' => 'ativo'])
                    : $posto->forceFill(['tipo' => $dados['tipo']])->save();
            }
        });
    }
}
