<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Portaria extends Component
{
    /** @var list<array{severity: string, title: string, description: string}> */
    public array $alerts = [
        ['severity' => 'warning', 'title' => '3 pré-cadastros aguardando análise há mais de 24 horas', 'description' => 'Solicitações de visitantes pendentes de decisão da portaria.'],
        ['severity' => 'danger', 'title' => 'Controladora do Portão de Serviço sem comunicação', 'description' => 'Última sincronização às 14:52. Operação em modo de contingência.'],
    ];

    /** @var array{status: string, code: string, openedAt: string, openedBy: string, total: float} */
    public array $cashRegister = [
        'status' => 'aberto',
        'code' => 'Caixa 01',
        'openedAt' => '13:00',
        'openedBy' => 'Tatiane Souza',
        'total' => 486.50,
    ];

    /**
     * Atalhos provisórios do Modo Portaria — aguardando confirmação da equipe
     * (ver docs/013_PLANO_DE_DIVISAO_DO_DESENVOLVIMENTO.md, seção 8).
     *
     * @var list<array{label: string, description: string, icon: string, route: string|null}>
     */
    public array $shortcuts = [
        ['label' => 'Validar entrada', 'description' => 'Identificar pessoa ou veículo e liberar acesso', 'icon' => 'shield', 'route' => null],
        ['label' => 'Pré-cadastros pendentes', 'description' => 'Analisar solicitações antecipadas de visitantes', 'icon' => 'badge-check', 'route' => null],
        ['label' => 'Cadastro rápido', 'description' => 'Criar cadastro mínimo durante o atendimento', 'icon' => 'users', 'route' => null],
        ['label' => 'Consultar caixa', 'description' => 'Ver movimentações e conferir o turno atual', 'icon' => 'wallet', 'route' => null],
    ];

    /** @var list<array{time: string, name: string, relation: string, subject: string, result: string}> */
    public array $recentAttendances = [
        ['time' => '16:04', 'name' => 'Luciana Ferraz', 'relation' => 'Prestador', 'subject' => 'Validação de entrada — Portão de Serviço', 'result' => 'pendente'],
        ['time' => '15:58', 'name' => 'Eduardo Nogueira', 'relation' => 'Morador', 'subject' => 'Validação de saída — Portaria Principal', 'result' => 'liberado'],
        ['time' => '15:57', 'name' => 'Camila Andrade', 'relation' => 'Visitante', 'subject' => 'Pré-cadastro recebido — Bloco B, Apto 304', 'result' => 'aguardando'],
        ['time' => '15:41', 'name' => 'Bianca Moretti', 'relation' => 'Visitante', 'subject' => 'Validação de entrada — Portaria Principal', 'result' => 'negado'],
        ['time' => '13:00', 'name' => 'Tatiane Souza', 'relation' => 'Operador de portaria', 'subject' => 'Abertura de caixa — Caixa 01', 'result' => 'liberado'],
    ];

    public function render(): View
    {
        return view('livewire.portaria')
            ->layout('components.layouts.app', [
                'title' => 'Modo Portaria',
                'heading' => 'Modo Portaria',
                'headingDescription' => 'Operador de portaria · Portaria Principal',
            ]);
    }
}
