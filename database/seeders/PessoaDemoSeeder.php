<?php

namespace Database\Seeders;

use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use Illuminate\Database\Seeder;

/**
 * Reaproveita os mesmos nomes de responsável já usados como dado de
 * demonstração em App\Support\DestinationDirectory e nos seeders de
 * pré-cadastro/imóveis, para manter o conjunto de dados de demonstração
 * consistente entre os módulos.
 */
class PessoaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (Pessoa::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $pessoas = [
            ['nome' => 'Marcos Vinicius da Silva', 'cpf' => '11122233396', 'telefone' => '(12) 99811-2233'],
            ['nome' => 'Eduardo Nogueira', 'cpf' => '22233344497', 'telefone' => '(12) 99822-3344'],
            ['nome' => 'Bianca Moretti', 'cpf' => '33344455598', 'telefone' => '(12) 99833-4455'],
            ['nome' => 'Mariana Souza', 'cpf' => '44455566699', 'telefone' => '(12) 99844-5566'],
            ['nome' => 'Rafael Domingues', 'cpf' => '55566677790', 'telefone' => '(12) 99855-6677'],
        ];

        foreach ($pessoas as $dados) {
            $pessoa = Pessoa::query()->create([
                'implantacao_id' => $implantacaoId,
                'nome' => $dados['nome'],
                'status' => 'ativo',
                'versao' => 1,
            ]);

            PessoaDocumento::query()->create([
                'implantacao_id' => $implantacaoId,
                'pessoa_id' => $pessoa->id,
                'tipo' => 'cpf',
                'pais' => 'BR',
                'valor_normalizado' => $dados['cpf'],
                'valor_apresentacao' => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $dados['cpf']),
                'status' => 'ativo',
                'started_at' => now(),
            ]);

            PessoaContato::query()->create([
                'implantacao_id' => $implantacaoId,
                'pessoa_id' => $pessoa->id,
                'tipo' => 'telefone',
                'valor' => $dados['telefone'],
                'principal' => true,
                'verificado' => false,
                'started_at' => now(),
            ]);
        }
    }
}
