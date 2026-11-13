<div class="property-management pessoa-consulta">
    @php($ficha = $this->ficha)
    @if (! $ficha)
        @php($contagens = $this->contagens)
        @php($pessoas = $this->pessoas)
        <section class="property-summary-grid" aria-label="Resumo das pessoas">
            <article><span>Pessoas cadastradas</span><strong>{{ $contagens['total'] }}</strong><small>Nesta implantação</small></article>
            <article><span>Cadastros ativos</span><strong>{{ $contagens['ativos'] }}</strong><small>Situação ativa</small></article>
            <article><span>Podem entrar agora</span><strong>{{ $contagens['vigentes'] }}</strong><small>Vínculo ou autorização vigente</small></article>
            <article><span>Sem foto</span><strong>{{ $contagens['sem_foto'] }}</strong><small>Tire a foto no próximo atendimento</small></article>
        </section>

        <section class="property-list-card" aria-labelledby="pessoas-lista-titulo">
            <header>
                <div><h2 id="pessoas-lista-titulo">Consulta de pessoas</h2><p>Busque, abra a ficha e corrija os dados de quem já está cadastrado.</p></div>
                <x-ui.button variant="primary" :href="route('people.create')">Nova pessoa</x-ui.button>
            </header>

            <div class="property-list-filters pessoa-consulta__filtros">
                <label class="property-search">
                    <span class="sr-only">Buscar pessoas</span>
                    <x-icon name="search" />
                    <input id="pessoas-busca" type="search" wire:model.live.debounce.400ms="busca" maxlength="80" autocomplete="off" placeholder="Nome, CPF completo, telefone, placa ou código do imóvel">
                </label>
                <x-ui.select id="pessoas-filtro-tipo" label="Tipo de acesso" wire:model.live="filtroTipo">
                    <option value="">Todos os tipos</option>
                    @foreach (\App\Livewire\PessoaConsulta::TIPOS as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select id="pessoas-filtro-situacao" label="Situação" wire:model.live="filtroSituacao">
                    <option value="">Todas as situações</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                    <option value="bloqueado">Bloqueado</option>
                </x-ui.select>
                <div class="pessoa-consulta__marcas">
                    <x-ui.checkbox id="pessoas-so-vigentes" label="Só autorização vigente" wire:model.live="soVigentes" />
                    <x-ui.checkbox id="pessoas-so-sem-foto" label="Só sem foto" wire:model.live="soSemFoto" />
                </div>
            </div>

            <div wire:loading.flex wire:target="busca,filtroTipo,filtroSituacao,soVigentes,soSemFoto" class="validation-search__state" aria-live="polite"><x-icon name="refresh" /> Buscando…</div>

            <x-ui.responsive-table
                label="Lista de pessoas"
                :state="$pessoas->count() ? 'ready' : 'empty'"
                empty-title="Nenhuma pessoa encontrada"
                empty-description="Revise a busca ou os filtros. O CPF precisa ser digitado completo."
            >
                <x-slot:table>
                    <thead><tr><th>Pessoa</th><th>CPF</th><th>Tipo</th><th>Imóvel / destino</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($pessoas as $pessoa)
                            <tr wire:key="pessoa-{{ $pessoa['id'] }}">
                                <td>
                                    <div class="pessoa-consulta__nome">
                                        @if ($pessoa['foto_url'])
                                            <img class="validation-search__photo" src="{{ $pessoa['foto_url'] }}" alt="Foto de {{ $pessoa['nome'] }}" loading="lazy" referrerpolicy="no-referrer">
                                        @else
                                            <span class="validation-search__photo is-empty" title="{{ $pessoa['foto_estado'] === 'sem_permissao' ? 'Foto protegida pelo seu perfil' : 'Sem foto cadastrada' }}">{{ $pessoa['iniciais'] }}</span>
                                        @endif
                                        <strong>{{ $pessoa['nome'] }}</strong>
                                    </div>
                                </td>
                                <td>{{ $pessoa['cpf'] }}</td>
                                <td>{{ $pessoa['tipo'] }}</td>
                                <td>{{ $pessoa['imovel'] }}</td>
                                <td><x-ui.badge :variant="$pessoa['situacao_tom']">{{ $pessoa['situacao'] }}</x-ui.badge></td>
                                <td><x-ui.button variant="secondary" size="sm" wire:click="abrir('{{ $pessoa['id'] }}')">Ver ficha</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="property-mobile-list">
                        @foreach ($pessoas as $pessoa)
                            <li wire:key="pessoa-card-{{ $pessoa['id'] }}">
                                <header>
                                    <div><strong>{{ $pessoa['nome'] }}</strong><small>{{ $pessoa['cpf'] }}</small></div>
                                    <x-ui.badge :variant="$pessoa['situacao_tom']">{{ $pessoa['situacao'] }}</x-ui.badge>
                                </header>
                                <dl><div><dt>Tipo</dt><dd>{{ $pessoa['tipo'] }}</dd></div><div><dt>Imóvel / destino</dt><dd>{{ $pessoa['imovel'] }}</dd></div></dl>
                                <footer><x-ui.button variant="secondary" size="sm" wire:click="abrir('{{ $pessoa['id'] }}')">Ver ficha</x-ui.button></footer>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>

            <footer class="property-list-footer">
                <span>Exibindo {{ $pessoas->count() }} de {{ $pessoas->total() }} pessoas</span>
                @if ($busca !== '' || $filtroTipo !== '' || $filtroSituacao !== '' || $soVigentes || $soSemFoto)
                    <button type="button" class="sensitive-reveal" wire:click="limparFiltros">Limpar busca e filtros</button>
                @endif
            </footer>
            @if ($pessoas->hasPages())
                <div class="pessoa-consulta__paginas">{{ $pessoas->links() }}</div>
            @endif
        </section>
    @else
        <nav class="property-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="voltarParaLista">Pessoas</button><x-icon name="chevron-right" /><span aria-current="page">{{ $ficha['nome'] }}</span></nav>

        <section class="property-detail-hero pessoa-ficha__topo">
            <div class="pessoa-ficha__identidade">
                @if ($ficha['foto_url'])
                    <figure class="pessoa-ficha__foto">
                        <img src="{{ $ficha['foto_url'] }}" alt="Foto de {{ $ficha['nome'] }}">
                        <figcaption>{{ $ficha['foto_origem'] }}</figcaption>
                    </figure>
                @else
                    <span class="pessoa-ficha__foto is-empty" title="{{ $ficha['foto_estado'] === 'sem_permissao' ? 'Foto protegida pelo seu perfil' : 'Sem foto cadastrada' }}">{{ $ficha['iniciais'] }}</span>
                @endif
                <div>
                    <span>Pessoa</span>
                    <h2>{{ $ficha['nome'] }}</h2>
                    <p>{{ $ficha['tipo'] }}{{ $ficha['cadastrado_em'] ? ' · cadastrada em '.$ficha['cadastrado_em'] : '' }}{{ $ficha['foto_estado'] === 'sem_foto' ? ' · sem foto' : '' }}</p>
                </div>
            </div>
            <div class="property-detail-hero__actions">
                <x-ui.badge :variant="$ficha['situacao_tom']">{{ $ficha['situacao'] }}</x-ui.badge>
                <x-ui.button variant="ghost" wire:click="voltarParaLista">Voltar à lista</x-ui.button>
            </div>
        </section>

        @php($dadosIdent = $this->dadosIdentificacao)
        <section class="validation-person-data" aria-labelledby="pessoa-dados-titulo" wire:key="pessoa-dados-{{ $currentPessoaId }}">
            <header>
                <div>
                    <h3 id="pessoa-dados-titulo">Dados pessoais</h3>
                    <p>O que já está no cadastro aparece em verde. Correções guardam os valores anteriores no histórico.</p>
                </div>
                @if (! $editandoIdentificacao && $this->podeEditarIdentificacao())
                    <x-ui.button variant="secondary" size="sm" wire:click="editarIdentificacao">Editar dados</x-ui.button>
                @endif
            </header>

            @if ($dadosIdentificacaoSalvos)
                <x-ui.alert variant="success" title="Dados corrigidos">O cadastro da pessoa foi atualizado. Os valores anteriores ficam no histórico.</x-ui.alert>
            @endif

            @if ($editandoIdentificacao)
                <div class="registration-fields">
                    <x-ui.field id="ident-nome" label="Nome completo" wire:model="identNome" :error="$errors->first('identNome')" required />
                    <x-ui.field id="ident-nome-social" label="Nome social (opcional)" wire:model="identNomeSocial" :error="$errors->first('identNomeSocial')" />
                    <x-ui.field id="ident-nascimento" type="date" label="Data de nascimento" wire:model="identNascimento" :error="$errors->first('identNascimento')" />
                    <x-ui.field id="ident-cpf" label="CPF" wire:model="identCpf" inputmode="numeric" :error="$errors->first('identCpf')" help="Corrija se estiver diferente do documento." />
                    <x-ui.field id="ident-telefone" type="tel" label="Telefone" wire:model="identTelefone" :error="$errors->first('identTelefone')" help="Com DDD. Deixe em branco se não houver." />
                    <x-ui.field id="ident-email" type="email" label="E-mail" wire:model="identEmail" :error="$errors->first('identEmail')" />
                </div>
                <div class="equipamento-management-acoes">
                    <x-ui.button variant="ghost" size="sm" wire:click="cancelarEdicaoIdentificacao">Cancelar</x-ui.button>
                    <x-ui.button variant="success" size="sm" wire:click="salvarIdentificacao" wire:loading.attr="disabled" wire:target="salvarIdentificacao">Salvar correção</x-ui.button>
                </div>
            @elseif ($dadosIdent)
                <dl class="validation-person-data__campos">
                    <div class="is-ok"><dt><x-icon name="check-circle" /> Nome completo</dt><dd>{{ $dadosIdent['nome'] }}</dd></div>
                    @if ($dadosIdent['nome_social'])<div class="is-ok"><dt><x-icon name="check-circle" /> Nome social</dt><dd>{{ $dadosIdent['nome_social'] }}</dd></div>@endif
                    <div @class(['is-ok' => $dadosIdent['nascimento']])><dt>@if ($dadosIdent['nascimento'])<x-icon name="check-circle" /> @endif Data de nascimento</dt><dd>{{ $dadosIdent['nascimento'] ?? 'Não informada' }}</dd></div>
                    <div @class(['is-ok' => $dadosIdent['cpf']])><dt>@if ($dadosIdent['cpf'])<x-icon name="check-circle" /> @endif CPF</dt><dd>{{ $dadosIdent['cpf'] ? \App\Support\NormalizadorBusca::mascararDocumento($dadosIdent['cpf']) : 'Não informado' }}</dd></div>
                    <div @class(['is-ok' => $dadosIdent['telefone']])><dt>@if ($dadosIdent['telefone'])<x-icon name="check-circle" /> @endif Telefone</dt><dd>{{ $dadosIdent['telefone'] ?? 'Não informado' }}</dd></div>
                    <div @class(['is-ok' => $dadosIdent['email']])><dt>@if ($dadosIdent['email'])<x-icon name="check-circle" /> @endif E-mail</dt><dd>{{ $dadosIdent['email'] ?? 'Não informado' }}</dd></div>
                </dl>
            @endif
        </section>

        <section class="property-detail-grid pessoa-ficha__grade">
            <article class="property-section-card">
                <header><div><h3>Vínculos com imóveis</h3><p>Morador, proprietário, prestador… com a vigência de cada um.</p></div></header>
                @forelse ($ficha['vinculos'] as $vinculo)
                    <div class="pessoa-ficha__linha">
                        <div><strong>{{ $vinculo['tipo'] }} · {{ $vinculo['imovel'] }}</strong><small>{{ $vinculo['vigencia'] }}</small></div>
                        <x-ui.badge :variant="$vinculo['ativo'] ? 'success' : 'neutral'">{{ $vinculo['ativo'] ? 'Ativo' : ucfirst($vinculo['status']) }}</x-ui.badge>
                    </div>
                @empty
                    <p class="pessoa-ficha__vazio">Nenhum vínculo com imóvel.</p>
                @endforelse
            </article>

            <article class="property-section-card">
                <header><div><h3>Autorizações</h3><p>Turista, visitante e as dadas pelo porteiro, com o prazo.</p></div></header>
                @forelse ($ficha['autorizacoes'] as $autorizacao)
                    <div class="pessoa-ficha__linha">
                        <div>
                            <strong>{{ $autorizacao['tipo'] }}{{ $autorizacao['imovel'] ? ' · '.$autorizacao['imovel'] : '' }}</strong>
                            <small>{{ $autorizacao['periodo'] }}{{ $autorizacao['responsavel'] ? ' · responsável: '.$autorizacao['responsavel'] : '' }}</small>
                        </div>
                        <x-ui.badge :variant="$autorizacao['vigente'] ? 'success' : 'neutral'">{{ $autorizacao['status'] }}</x-ui.badge>
                    </div>
                @empty
                    <p class="pessoa-ficha__vazio">Nenhuma autorização registrada.</p>
                @endforelse
            </article>

            <article class="property-section-card">
                <header><div><h3>Veículos</h3><p>Veículos vinculados a esta pessoa.</p></div></header>
                @forelse ($ficha['veiculos'] as $veiculo)
                    <div class="pessoa-ficha__linha"><div><strong>{{ $veiculo['placa'] }}</strong><small>{{ $veiculo['descricao'] }}</small></div></div>
                @empty
                    <p class="pessoa-ficha__vazio">Nenhum veículo vinculado.</p>
                @endforelse
            </article>

            <article class="property-section-card">
                <header><div><h3>Últimos acessos</h3><p>Os 10 atendimentos mais recentes na portaria.</p></div></header>
                @forelse ($ficha['acessos'] as $acesso)
                    <div class="pessoa-ficha__linha">
                        <div><strong>{{ $acesso['quando'] }}</strong><small>Protocolo {{ $acesso['protocolo'] }}</small></div>
                        <x-ui.badge :variant="$acesso['tom']">{{ $acesso['resultado'] }}</x-ui.badge>
                    </div>
                @empty
                    <p class="pessoa-ficha__vazio">Nenhum atendimento registrado.</p>
                @endforelse
            </article>
        </section>
    @endif
</div>
