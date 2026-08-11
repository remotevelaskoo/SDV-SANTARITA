<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\EmpresaDocumento;
use App\Models\EmpresaPrestador;
use App\Models\EmpresaServico;
use App\Models\Implantacao;
use App\Models\Pessoa;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração das empresas, com os mesmos CNPJs e nomes já usados
 * no protótipo de CompanyManagement.php (P13). Prestadores só são ligados
 * quando a Pessoa correspondente já existe (PessoaDemoSeeder) — mesmo
 * critério já usado no VinculoDemoSeeder/VeiculoDemoSeeder. Sérgio Aparecido
 * Luz, Luciana Ferraz e Marta Oliveira Santos ficam sem vínculo real por
 * esse motivo.
 */
class EmpresaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (Empresa::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $empresas = [
            [
                'cnpj' => '12.345.678/0001-90',
                'razao_social' => 'Manutenção Predial Vale Ltda',
                'nome_fantasia' => 'ValeManutenção',
                'categoria' => 'manutencao',
                'status' => 'ativo',
                'telefone' => '(24) 3344-5566',
                'email' => 'contato@valemanutencao.com.br',
                'documentos' => [
                    ['tipo' => 'Contrato de prestação de serviço', 'status' => 'validado'],
                    ['tipo' => 'Comprovante de CNPJ', 'status' => 'validado'],
                    ['tipo' => 'Certidão negativa de débitos', 'status' => 'enviado'],
                ],
                'servicos' => [
                    ['atividade' => 'Manutenção elétrica', 'status' => 'autorizado'],
                    ['atividade' => 'Manutenção hidráulica', 'status' => 'autorizado'],
                ],
                'prestadores' => [],
            ],
            [
                'cnpj' => '23.456.789/0001-11',
                'razao_social' => 'Limpa Fácil Serviços de Limpeza Ltda',
                'nome_fantasia' => 'Limpa Fácil',
                'categoria' => 'limpeza',
                'status' => 'ativo',
                'telefone' => '(24) 3322-1100',
                'email' => 'financeiro@limpafacil.com.br',
                'documentos' => [
                    ['tipo' => 'Contrato de prestação de serviço', 'status' => 'validado'],
                    ['tipo' => 'Certidão negativa de débitos', 'status' => 'vencendo'],
                ],
                'servicos' => [
                    ['atividade' => 'Limpeza de áreas comuns', 'status' => 'autorizado'],
                ],
                'prestadores' => [],
            ],
            [
                'cnpj' => '34.567.890/0001-22',
                'razao_social' => 'Vale Segurança Patrimonial Ltda',
                'nome_fantasia' => 'Vale Segurança',
                'categoria' => 'seguranca',
                'status' => 'ativo',
                'telefone' => '(24) 3355-7788',
                'email' => 'operacoes@valeseguranca.com.br',
                'documentos' => [
                    ['tipo' => 'Contrato de prestação de serviço', 'status' => 'validado'],
                    ['tipo' => 'Alvará de funcionamento', 'status' => 'validado'],
                    ['tipo' => 'Comprovante de CNPJ', 'status' => 'validado'],
                ],
                'servicos' => [
                    ['atividade' => 'Rondas patrimoniais', 'status' => 'autorizado'],
                    ['atividade' => 'Monitoramento de câmeras', 'status' => 'autorizado'],
                ],
                'prestadores' => [
                    ['nome' => 'Eduardo Nogueira', 'atividade' => 'Vigilante', 'status' => 'ativo'],
                    ['nome' => 'Rafael Domingues', 'atividade' => 'Supervisor', 'status' => 'ativo'],
                ],
            ],
            [
                'cnpj' => '45.678.901/0001-33',
                'razao_social' => 'Jardim Verde Paisagismo Ltda',
                'nome_fantasia' => 'Jardim Verde',
                'categoria' => 'jardinagem',
                'status' => 'inativo',
                'telefone' => '(24) 3300-9911',
                'email' => 'contato@jardimverde.com.br',
                'documentos' => [
                    ['tipo' => 'Contrato de prestação de serviço', 'status' => 'expirado'],
                ],
                'servicos' => [
                    ['atividade' => 'Paisagismo e jardinagem', 'status' => 'suspenso'],
                ],
                'prestadores' => [
                    ['nome' => 'Bianca Moretti', 'atividade' => 'Jardineira', 'status' => 'encerrado', 'ended_at' => now()->subDays(52)],
                ],
            ],
        ];

        foreach ($empresas as $dados) {
            $empresa = Empresa::query()->create([
                'implantacao_id' => $implantacaoId,
                'cnpj' => $dados['cnpj'],
                'razao_social' => $dados['razao_social'],
                'nome_fantasia' => $dados['nome_fantasia'],
                'categoria' => $dados['categoria'],
                'status' => $dados['status'],
                'telefone' => $dados['telefone'],
                'email' => $dados['email'],
                'versao' => 1,
            ]);

            foreach ($dados['documentos'] as $documento) {
                EmpresaDocumento::query()->create([
                    'implantacao_id' => $implantacaoId,
                    'empresa_id' => $empresa->id,
                    'tipo' => $documento['tipo'],
                    'status' => $documento['status'],
                ]);
            }

            foreach ($dados['servicos'] as $servico) {
                EmpresaServico::query()->create([
                    'implantacao_id' => $implantacaoId,
                    'empresa_id' => $empresa->id,
                    'atividade' => $servico['atividade'],
                    'status' => $servico['status'],
                ]);
            }

            foreach ($dados['prestadores'] as $prestador) {
                $pessoa = Pessoa::query()->where('nome', $prestador['nome'])->first();

                if ($pessoa === null) {
                    continue;
                }

                EmpresaPrestador::query()->create([
                    'implantacao_id' => $implantacaoId,
                    'empresa_id' => $empresa->id,
                    'pessoa_id' => $pessoa->id,
                    'atividade' => $prestador['atividade'],
                    'status' => $prestador['status'],
                    'started_at' => now()->subYear(),
                    'ended_at' => $prestador['ended_at'] ?? null,
                    'versao' => 1,
                ]);
            }
        }
    }
}
