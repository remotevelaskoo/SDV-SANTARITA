<?php

namespace App\Console\Commands;

use App\Services\Gestao\PainelGestaoNuvem;
use Illuminate\Console\Command;

class AcessoRemotoGestao extends Command
{
    protected $signature = 'gestao:acesso-remoto {email : E-mail de um usuário com acesso ao Dashboard}';

    protected $description = 'Cria ou troca a senha do painel de gestão na nuvem. A senha não fica guardada no servidor local.';

    public function handle(PainelGestaoNuvem $painel): int
    {
        $senha = (string) $this->secret('Senha do painel remoto (mínimo 10 caracteres)');
        if (mb_strlen($senha) < 10 || $senha !== (string) $this->secret('Repita a senha')) {
            $this->error('Senha curta ou diferente na repetição.');

            return self::FAILURE;
        }
        // Terminais do Windows via docker podem mandar caracteres invisíveis
        // junto com a senha; aí o painel nunca aceita o que é digitado no navegador.
        if (! mb_check_encoding($senha, 'UTF-8') || preg_match('/\p{C}/u', $senha)) {
            $this->error('A senha chegou com caracteres invisíveis ou acentos alterados pelo terminal. Use só letras sem acento e números.');

            return self::FAILURE;
        }
        $this->line('Senha recebida com '.mb_strlen($senha).' caracteres.');

        try {
            $painel->definirAcesso((string) $this->argument('email'), $senha);
            $this->info('Acesso remoto pronto para '.$this->argument('email').'.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Acesso não criado: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
