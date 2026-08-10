<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public string $period = 'hoje';

    /** @var array<string, bool> */
    public array $cameraStatus = [
        'cam-01' => true,
        'cam-02' => true,
        'cam-03' => true,
        'cam-04' => true,
    ];

    /** @var list<array<string, mixed>> */
    public array $metrics = [
        ['label' => 'Pessoas cadastradas', 'value' => 4182, 'type' => 'number', 'variation' => 2.4, 'trend' => 'up', 'comparison' => 'vs. mês anterior', 'period' => 'Base atual', 'updated' => '16:02', 'link' => true],
        ['label' => 'Visitantes hoje', 'value' => 137, 'type' => 'number', 'variation' => 11.8, 'trend' => 'up', 'comparison' => 'vs. mesmo dia da semana', 'period' => 'Hoje, desde 00h00', 'updated' => '16:02', 'link' => true],
        ['label' => 'Entradas hoje', 'value' => 612, 'type' => 'number', 'variation' => 4.1, 'trend' => 'up', 'comparison' => 'vs. ontem', 'period' => 'Hoje, desde 00h00', 'updated' => '16:02', 'link' => true],
        ['label' => 'Saídas hoje', 'value' => 574, 'type' => 'number', 'variation' => 1.3, 'trend' => 'down', 'comparison' => 'vs. ontem', 'period' => 'Hoje, desde 00h00', 'updated' => '16:02', 'link' => true],
        ['label' => 'Moradores', 'value' => 2914, 'type' => 'number', 'variation' => 0.0, 'trend' => 'stable', 'comparison' => 'vs. mês anterior', 'period' => 'Vínculos vigentes', 'updated' => '16:00', 'link' => true],
        ['label' => 'Prestadores', 'value' => 268, 'type' => 'number', 'variation' => 6.7, 'trend' => 'up', 'comparison' => 'vs. mês anterior', 'period' => 'Autorizações vigentes', 'updated' => '16:00', 'link' => true],
        ['label' => 'Veículos cadastrados', 'value' => 1903, 'type' => 'number', 'variation' => 1.9, 'trend' => 'up', 'comparison' => 'vs. mês anterior', 'period' => 'Base atual', 'updated' => '16:00', 'link' => true],
        ['label' => 'Arrecadação hoje', 'value' => 3487.50, 'type' => 'currency', 'variation' => 8.2, 'trend' => 'up', 'comparison' => 'vs. ontem', 'period' => 'Turno do caixa aberto', 'updated' => '16:02', 'link' => false],
    ];

    /** @var list<array<string, string>> */
    public array $alerts = [
        ['severity' => 'warning', 'title' => '3 pré-cadastros aguardando análise há mais de 24 horas', 'description' => 'Solicitações de visitantes pendentes de decisão da administração.'],
        ['severity' => 'danger', 'title' => 'Controladora do Portão de Serviço sem comunicação', 'description' => 'Última sincronização às 14:52. Operação em modo de contingência.'],
    ];

    /** @var list<array<string, string>> */
    public array $accesses = [
        ['time' => '16:01', 'name' => 'Camila Andrade', 'document' => '•••.•••.331-07', 'relation' => 'Visitante', 'property' => 'Bloco B — Apto 304', 'point' => 'Portaria Principal', 'plate' => 'RQK8H21', 'type' => 'entrada', 'result' => 'liberado'],
        ['time' => '15:58', 'name' => 'Eduardo Nogueira', 'document' => '•••.•••.760-55', 'relation' => 'Morador', 'property' => 'Bloco A — Apto 112', 'point' => 'Portaria Principal', 'plate' => 'GFT4A09', 'type' => 'saida', 'result' => 'liberado'],
        ['time' => '15:54', 'name' => 'Luciana Ferraz', 'document' => '•••.•••.218-90', 'relation' => 'Prestador', 'property' => 'Área comum — Manutenção', 'point' => 'Portão de Serviço', 'plate' => '', 'type' => 'entrada', 'result' => 'pendente'],
        ['time' => '15:47', 'name' => 'Rafael Domingues', 'document' => '•••.•••.004-12', 'relation' => 'Inquilino', 'property' => 'Bloco C — Apto 501', 'point' => 'Portaria Principal', 'plate' => '', 'type' => 'entrada', 'result' => 'liberado'],
        ['time' => '15:41', 'name' => 'Bianca Moretti', 'document' => '•••.•••.615-38', 'relation' => 'Visitante', 'property' => 'Bloco A — Apto 208', 'point' => 'Portaria Principal', 'plate' => '', 'type' => 'entrada', 'result' => 'negado'],
        ['time' => '15:33', 'name' => 'Sérgio Aparecido Luz', 'document' => '•••.•••.447-61', 'relation' => 'Prestador', 'property' => 'Bloco B — Apto 706', 'point' => 'Portão de Serviço', 'plate' => 'LMD7C44', 'type' => 'entrada', 'result' => 'liberado'],
    ];

    /** @var array<string, list<array{label: string, entries: int, exits: int}>> */
    public array $series = [
        'hoje' => [
            ['label' => '00h', 'entries' => 8, 'exits' => 14],
            ['label' => '03h', 'entries' => 4, 'exits' => 6],
            ['label' => '06h', 'entries' => 41, 'exits' => 96],
            ['label' => '09h', 'entries' => 122, 'exits' => 71],
            ['label' => '12h', 'entries' => 158, 'exits' => 129],
            ['label' => '15h', 'entries' => 181, 'exits' => 148],
            ['label' => '18h', 'entries' => 74, 'exits' => 82],
            ['label' => '21h', 'entries' => 24, 'exits' => 28],
        ],
        '7dias' => [
            ['label' => 'Seg', 'entries' => 588, 'exits' => 561],
            ['label' => 'Ter', 'entries' => 604, 'exits' => 592],
            ['label' => 'Qua', 'entries' => 631, 'exits' => 608],
            ['label' => 'Qui', 'entries' => 612, 'exits' => 574],
            ['label' => 'Sex', 'entries' => 702, 'exits' => 688],
            ['label' => 'Sáb', 'entries' => 489, 'exits' => 512],
            ['label' => 'Dom', 'entries' => 352, 'exits' => 377],
        ],
        '30dias' => [
            ['label' => 'Sem. 1', 'entries' => 3921, 'exits' => 3844],
            ['label' => 'Sem. 2', 'entries' => 4108, 'exits' => 4021],
            ['label' => 'Sem. 3', 'entries' => 3987, 'exits' => 3902],
            ['label' => 'Sem. 4', 'entries' => 4212, 'exits' => 4160],
        ],
    ];

    /** @var list<array{id: string, title: string}> */
    public array $cameras = [
        ['id' => 'cam-01', 'title' => 'Portaria Principal'],
        ['id' => 'cam-02', 'title' => 'Garagem Subsolo'],
        ['id' => 'cam-03', 'title' => 'Pátio de Carga'],
        ['id' => 'cam-04', 'title' => 'Área de Lazer'],
    ];

    public function setPeriod(string $period): void
    {
        if (array_key_exists($period, $this->series)) {
            $this->period = $period;
        }
    }

    public function toggleCamera(string $cameraId): void
    {
        if (array_key_exists($cameraId, $this->cameraStatus)) {
            $this->cameraStatus[$cameraId] = ! $this->cameraStatus[$cameraId];
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard')
            ->layout('components.layouts.app', [
                'title' => 'Dashboard operacional',
            ]);
    }
}
