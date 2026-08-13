<?php

namespace Database\Seeders;

use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Semeia o catálogo real de permissões e os 4 perfis definidos pelo PO para o
 * P20 (PORTEIRO_CAIXA, GESTOR, AUDITOR, ADMINISTRADOR), e associa os
 * usuários demo (PortariaDemoSeeder) à implantação Santa Rita com o perfil
 * correspondente.
 */
class UsuarioDemoSeeder extends Seeder
{
    /** @var array<string, array{modulo: string, descricao: string}> */
    private const PERMISSOES = [
        'dashboard.visualizar' => ['modulo' => 'dashboard', 'descricao' => 'Visualizar o painel inicial'],
        'pessoas.gerenciar' => ['modulo' => 'pessoas', 'descricao' => 'Cadastrar e consultar pessoas'],
        'pre-registro.analisar' => ['modulo' => 'pre-cadastro', 'descricao' => 'Consultar a fila e a ficha de pré-cadastros'],
        'pre-registro.editar' => ['modulo' => 'pre-cadastro', 'descricao' => 'Corrigir dados de um pré-cadastro antes da aprovação'],
        'pre-registro.aprovar' => ['modulo' => 'pre-cadastro', 'descricao' => 'Aprovar um pré-cadastro'],
        'pre-registro.rejeitar' => ['modulo' => 'pre-cadastro', 'descricao' => 'Rejeitar um pré-cadastro'],
        'pre-registro.solicitar-correcao' => ['modulo' => 'pre-cadastro', 'descricao' => 'Solicitar correção de um pré-cadastro'],
        'validacao.registrar' => ['modulo' => 'validacao', 'descricao' => 'Registrar entradas e saídas'],
        'imoveis.consultar' => ['modulo' => 'imoveis', 'descricao' => 'Consultar imóveis e responsáveis'],
        'veiculos.consultar' => ['modulo' => 'veiculos', 'descricao' => 'Consultar veículos cadastrados'],
        'empresas.consultar' => ['modulo' => 'empresas', 'descricao' => 'Consultar empresas e prestadores'],
        'encomendas.registrar' => ['modulo' => 'encomendas', 'descricao' => 'Registrar recebimento de encomendas'],
        'contribuicao.registrar' => ['modulo' => 'contribuicao', 'descricao' => 'Registrar contribuições/taxas na portaria'],
        'caixa.proprio.gerenciar' => ['modulo' => 'caixa', 'descricao' => 'Abrir, movimentar e fechar o caixa próprio'],
        'relatorios.proprio.consultar' => ['modulo' => 'relatorios', 'descricao' => 'Consultar relatórios do próprio turno'],
        'integracao.encaminhar' => ['modulo' => 'integracao', 'descricao' => 'Encaminhar itens para a fila de integração'],
        'ocorrencias.registrar' => ['modulo' => 'ocorrencias', 'descricao' => 'Registrar ocorrências da portaria'],
        'manutencao.gerenciar' => ['modulo' => 'manutencao', 'descricao' => 'Registrar solicitações de manutenção'],
        'caixa.consolidado.consultar' => ['modulo' => 'caixa', 'descricao' => 'Consultar o caixa consolidado de todos os turnos'],
        'relatorios.consolidado.consultar' => ['modulo' => 'relatorios', 'descricao' => 'Consultar relatórios consolidados'],
        'autorizacoes.gerenciar' => ['modulo' => 'autorizacoes', 'descricao' => 'Gerenciar autorizações excepcionais de acesso'],
        'usuarios.administrar' => ['modulo' => 'usuarios', 'descricao' => 'Administrar usuários do sistema'],
        'perfis.administrar' => ['modulo' => 'usuarios', 'descricao' => 'Administrar perfis e permissões'],
        'configuracoes.gerenciar' => ['modulo' => 'configuracoes', 'descricao' => 'Gerenciar configurações da implantação'],
        'integracoes.gerenciar' => ['modulo' => 'integracoes', 'descricao' => 'Gerenciar integrações externas'],
        'auditoria.consultar' => ['modulo' => 'auditoria', 'descricao' => 'Consultar logs e detalhes da auditoria'],
        'auditoria.exportar' => ['modulo' => 'auditoria', 'descricao' => 'Exportar registros de auditoria'],
        'arquivos.sensiveis.visualizar' => ['modulo' => 'arquivos', 'descricao' => 'Visualizar documentos, fotos e selfies protegidos'],
    ];

    /** @var list<string> */
    private const PORTEIRO_CAIXA = [
        'dashboard.visualizar',
        'pessoas.gerenciar',
        'pre-registro.analisar',
        'pre-registro.editar',
        'pre-registro.aprovar',
        'pre-registro.rejeitar',
        'pre-registro.solicitar-correcao',
        'arquivos.sensiveis.visualizar',
        'validacao.registrar',
        'imoveis.consultar',
        'veiculos.consultar',
        'empresas.consultar',
        'encomendas.registrar',
        'contribuicao.registrar',
        'caixa.proprio.gerenciar',
        'relatorios.proprio.consultar',
        'integracao.encaminhar',
        'ocorrencias.registrar',
        'manutencao.gerenciar',
    ];

    /** @var list<string> */
    private const AUDITOR = [
        'dashboard.visualizar',
        'pre-registro.analisar',
        'imoveis.consultar',
        'veiculos.consultar',
        'empresas.consultar',
        'caixa.consolidado.consultar',
        'relatorios.consolidado.consultar',
        'auditoria.consultar',
        'auditoria.exportar',
    ];

    public function run(): void
    {
        $this->call(FoundationSeeder::class);
        $this->call(PortariaDemoSeeder::class);

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $permissoes = collect(self::PERMISSOES)->map(
            fn (array $dados, string $chave) => Permissao::query()->firstOrCreate(['chave' => $chave], $dados)
        );

        $grants = [
            'Porteiro/Caixa' => self::PORTEIRO_CAIXA,
            'Gestor' => [...self::PORTEIRO_CAIXA, 'caixa.consolidado.consultar', 'relatorios.consolidado.consultar', 'autorizacoes.gerenciar'],
            'Auditor' => self::AUDITOR,
            'Administrador' => array_keys(self::PERMISSOES),
        ];

        $perfis = [];

        foreach ($grants as $nome => $chaves) {
            $perfil = Perfil::query()->firstOrCreate(
                ['implantacao_id' => $implantacaoId, 'nome' => $nome],
                ['status' => 'ativo']
            );

            foreach (array_unique($chaves) as $chave) {
                $permissao = $permissoes->get($chave);

                if (! $perfil->permissoes()->where('permissoes.id', $permissao->id)->exists()) {
                    $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => $implantacaoId]);
                }
            }

            $perfis[$nome] = $perfil;
        }

        $atribuicoes = [
            'portaria' => 'Porteiro/Caixa',
            'portaria.leitura' => 'Auditor',
            'gestor' => 'Gestor',
            'administrador' => 'Administrador',
        ];

        foreach ($atribuicoes as $username => $nomePerfil) {
            $user = User::query()->where('username', $username)->first();

            if ($user === null) {
                continue;
            }

            UsuarioImplantacao::query()->firstOrCreate([
                'implantacao_id' => $implantacaoId,
                'user_id' => $user->id,
            ], ['status' => 'ativa']);

            $perfil = $perfis[$nomePerfil];

            if (! UsuarioPerfil::query()->where('user_id', $user->id)->where('perfil_id', $perfil->id)->whereNull('ended_at')->exists()) {
                UsuarioPerfil::query()->create([
                    'implantacao_id' => $implantacaoId,
                    'user_id' => $user->id,
                    'perfil_id' => $perfil->id,
                    'started_at' => now(),
                ]);
            }
        }
    }
}
