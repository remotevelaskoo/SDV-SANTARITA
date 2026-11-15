<?php

namespace App\Services\Gestao;

use App\Models\Implantacao;
use App\Models\User;
use App\Services\Hibrido\SincronizacaoIndisponivel;
use App\Support\ImplantacaoContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Painel de gestão fora do condomínio (PO 15/11/2026). O servidor local
 * empurra os totais para a nuvem por HTTPS de saída, pelo mesmo canal do
 * pré-cadastro; nenhuma porta do condomínio fica aberta para a internet.
 * Quem pode entrar no painel remoto é quem tem o Dashboard aqui.
 */
class PainelGestaoNuvem
{
    public const PERMISSAO = 'dashboard.visualizar';

    public function __construct(private MetricasDia $metricas) {}

    /** Publica hoje e ontem (ontem fecha caixas que viraram a meia-noite). @return list<string> */
    public function publicar(): array
    {
        [$implantacao, $http, $url] = $this->conexao();

        return ImplantacaoContext::executarNo($implantacao, function () use ($http, $url): array {
            $dias = [];
            foreach ([now()->subDay(), now()] as $dia) {
                $dados = $this->metricas->calcular($dia);
                $this->enviar($http, $url, ['action' => 'metricas', 'dia' => $dados['dia'], 'dados' => $dados]);
                $dias[] = $dados['dia'];
            }
            // Quem perdeu o Dashboard aqui perde o painel remoto no próximo ciclo.
            $this->enviar($http, $url, ['action' => 'gestores', 'emails' => $this->emailsAutorizados()]);

            return $dias;
        });
    }

    /** Cria ou troca a senha do acesso remoto de um usuário com Dashboard. */
    public function definirAcesso(string $email, string $senha): void
    {
        [$implantacao, $http, $url] = $this->conexao();
        ImplantacaoContext::executarNo($implantacao, function () use ($http, $url, $email, $senha): void {
            $email = mb_strtolower(trim($email));
            if (! in_array($email, $this->emailsAutorizados(), true)) {
                throw new SincronizacaoIndisponivel('Esse e-mail não é de um usuário ativo com acesso ao Dashboard.');
            }
            $usuario = User::query()->whereRaw('lower(email) = ?', [$email])->firstOrFail();
            $this->enviar($http, $url, ['action' => 'gestor', 'email' => $email, 'nome' => $usuario->name, 'senha' => $senha]);
        });
    }

    /** @return list<string> */
    public function emailsAutorizados(): array
    {
        return User::query()->where('status', 'ativo')->whereNotNull('email')->get()
            ->filter(fn (User $usuario) => $usuario->hasPermission(self::PERMISSAO))
            ->map(fn (User $usuario) => mb_strtolower(trim($usuario->email)))
            ->unique()->sort()->values()->all();
    }

    /** @return array{0: Implantacao, 1: PendingRequest, 2: string} */
    private function conexao(): array
    {
        if (! config('pre_cadastro_nuvem.gestao_enabled')) {
            throw new SincronizacaoIndisponivel('Painel de gestão na nuvem desativado.');
        }
        $url = rtrim((string) config('pre_cadastro_nuvem.url'), '/');
        $token = (string) config('pre_cadastro_nuvem.token');
        if (! str_starts_with($url, 'https://') || strlen($token) < 32) {
            throw new SincronizacaoIndisponivel('Configure o endereço HTTPS e a credencial do nó.');
        }
        $implantacao = Implantacao::query()->whereKey(config('pre_cadastro_nuvem.implantacao_id'))->where('status', 'ativa')->firstOrFail();
        $http = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(30)->withOptions(['allow_redirects' => false]);

        return [$implantacao, $http, $url.'/api/gestao'];
    }

    /** @param array<string, mixed> $corpo */
    private function enviar(PendingRequest $http, string $url, array $corpo): void
    {
        $resposta = $http->post($url, $corpo);
        if (! $resposta->successful()) {
            $erro = $resposta->json('error');
            throw new SincronizacaoIndisponivel(is_string($erro) ? $erro : 'Nuvem indisponível; os dados locais foram preservados.');
        }
    }
}
