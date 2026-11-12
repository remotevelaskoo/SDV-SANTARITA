<?php

namespace Tests\Feature;

use App\Livewire\PreRegistrationQueue;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/** Pré-cadastro público divulgado a todos (decisão do PO, 11/11/2026): link fixo e QR code. */
class PreCadastroDivulgacaoTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    private const LINK = 'https://portal.example.test/#convite=abcdefghijklmnopqrstuvwxyz0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_fila_abre_o_portal_publico_configurado(): void
    {
        config(['pre_cadastro_nuvem.link_publico' => self::LINK]);
        $this->actingAs($this->operador('pre-registro.analisar'));
        Livewire::test(PreRegistrationQueue::class)
            ->assertSeeHtml('href="'.self::LINK.'"')
            ->assertSee('Link e QR code para divulgar')
            ->assertDontSeeHtml(route('pre-registration.public'));
    }

    public function test_sem_link_configurado_nao_mostra_botao_quebrado(): void
    {
        config(['pre_cadastro_nuvem.link_publico' => null]);
        $this->actingAs($this->operador('pre-registro.analisar'));
        Livewire::test(PreRegistrationQueue::class)
            ->assertDontSee('Abrir formulário público')
            ->assertSee('Link e QR code para divulgar');
    }

    public function test_pagina_de_divulgacao_gera_qr_code_local_para_imprimir(): void
    {
        config(['pre_cadastro_nuvem.link_publico' => self::LINK]);
        $this->actingAs($this->operador('pre-registro.analisar'));
        $this->get(route('pre-registrations.share'))
            ->assertOk()
            ->assertSee('Faça seu pré-cadastro antes de chegar')
            ->assertSee('Imprimir cartaz')
            ->assertSee(self::LINK)
            ->assertSee('<svg', false)
            ->assertDontSee('<?xml', false);
    }

    public function test_link_invalido_ou_sem_convite_nao_vira_qr_code(): void
    {
        $this->actingAs($this->operador('pre-registro.analisar'));
        foreach (['http://portal.example.test/#convite=abcdefghijklmnopqrstuvwxyz0123456789', 'https://portal.example.test/', 'https://portal.example.test/#convite=curto'] as $link) {
            config(['pre_cadastro_nuvem.link_publico' => $link]);
            $this->get(route('pre-registrations.share'))
                ->assertOk()
                ->assertSee('Link do pré-cadastro ainda não configurado')
                ->assertDontSee('<svg', false);
        }
    }

    public function test_pagina_exige_login_e_permissao(): void
    {
        $this->get(route('pre-registrations.share'))->assertRedirect(route('login'));
        $this->actingAs($this->operador('validacao.registrar'));
        $this->get(route('pre-registrations.share'))->assertRedirect(route('home'));
    }
}
