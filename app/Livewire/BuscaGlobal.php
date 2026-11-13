<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\BuscaGlobal as ServicoBuscaGlobal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Campo de busca global do cabeçalho: lista suspensa agrupada por tipo,
 * até 5 por grupo, navegação por teclado e "ver todos" no módulo.
 */
class BuscaGlobal extends Component
{
    public string $termo = '';

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function grupos(): array
    {
        $usuario = Auth::user();

        return $usuario instanceof User ? app(ServicoBuscaGlobal::class)->buscar($this->termo, $usuario) : [];
    }

    public function curto(): bool
    {
        return mb_strlen(trim($this->termo)) < ServicoBuscaGlobal::TAMANHO_MINIMO;
    }

    /** Destaca o termo no texto já escapado (seguro para {!! !!}). */
    public function destacar(string $texto): string
    {
        $seguro = e($texto);
        $termo = trim($this->termo);
        if ($termo === '' || mb_strlen($termo) < ServicoBuscaGlobal::TAMANHO_MINIMO) {
            return $seguro;
        }

        return preg_replace('/('.preg_quote(e($termo), '/').')/iu', '<mark>$1</mark>', $seguro) ?? $seguro;
    }

    public function render(): View
    {
        return view('livewire.busca-global');
    }
}
