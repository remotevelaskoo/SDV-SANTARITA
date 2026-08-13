<?php

namespace App\Observers;

use App\Models\Bloco;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Condominio;
use App\Models\Empresa;
use App\Models\EmpresaDocumento;
use App\Models\EmpresaPrestador;
use App\Models\EmpresaServico;
use App\Models\Encomenda;
use App\Models\EnderecoImovel;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\ImovelResponsabilidade;
use App\Models\Perfil;
use App\Models\PerfilPermissao;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\PessoaEndereco;
use App\Models\PreRegistration;
use App\Models\PreRegistrationEdit;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use App\Models\Vinculo;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /** @return list<class-string<Model>> */
    public static function observedModels(): array
    {
        return [
            Bloco::class, CaixaMovimentacao::class, CaixaTurno::class,
            Condominio::class, Empresa::class, EmpresaDocumento::class,
            EmpresaPrestador::class, EmpresaServico::class, Encomenda::class,
            EnderecoImovel::class, HistoricoAcesso::class, Imovel::class,
            ImovelResponsabilidade::class, Pessoa::class, PessoaContato::class,
            PessoaDocumento::class, PessoaEndereco::class, Perfil::class,
            PerfilPermissao::class, PreRegistration::class,
            PreRegistrationEdit::class, User::class, UsuarioImplantacao::class,
            UsuarioPerfil::class, Veiculo::class, VeiculoVinculo::class,
            Vinculo::class,
        ];
    }

    public function __construct(private AuditService $audit) {}

    public function created(Model $model): void
    {
        $this->audit->recordModelChange($model, 'criou');
    }

    public function updated(Model $model): void
    {
        if ($model->wasChanged()) {
            $this->audit->recordModelChange($model, 'alterou');
        }
    }

    public function deleted(Model $model): void
    {
        $this->audit->recordModelChange($model, 'excluiu');
    }
}
