<div class="company-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        @php
            $categoryLabels = ['manutencao' => 'Manutenção', 'limpeza' => 'Limpeza', 'seguranca' => 'Segurança', 'jardinagem' => 'Jardinagem', 'entregas' => 'Entregas', 'outro' => 'Outro'];
        @endphp

        <section class="company-summary-grid" aria-label="Resumo das empresas">
            <article><span>Empresas ativas</span><strong>{{ $companyCounts['active'] }}</strong><small>Podem receber novas autorizações</small></article>
            <article><span>Empresas inativas</span><strong>{{ $companyCounts['inactive'] }}</strong><small>Sem novas autorizações</small></article>
            <article><span>Prestadores vinculados</span><strong>{{ $companyCounts['providers'] }}</strong><small>Pessoas com vínculo ativo ou encerrado</small></article>
            <article><span>Documentos a vencer</span><strong>{{ $companyCounts['expiringDocuments'] }}</strong><small>Requerem atenção</small></article>
        </section>

        <section class="company-list-card" aria-labelledby="company-list-title">
            <header>
                <div><h2 id="company-list-title">Empresas e prestadores</h2><p>Consulte empresas, prestadores vinculados e autorizações por serviço.</p></div>
                <x-ui.button variant="primary" wire:click="createCompany">Cadastrar empresa</x-ui.button>
            </header>

            <div class="company-list-filters">
                <label class="company-search">
                    <span class="sr-only">Buscar empresas</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar razão social, nome fantasia ou CNPJ">
                </label>
                <x-ui.select id="company-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todas">Todas as situações</option>
                    <option value="ativo">Ativas</option>
                    <option value="inativo">Inativas</option>
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Lista de empresas"
                :state="count($filteredCompanies) ? 'ready' : 'empty'"
                empty-title="Nenhuma empresa encontrada"
                empty-description="Revise a busca ou selecione outra situação."
            >
                <x-slot:table>
                    <thead><tr><th>Empresa</th><th>CNPJ</th><th>Categoria</th><th>Situação</th><th>Prestadores</th><th>Atualização</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredCompanies as $company)
                            <tr>
                                <td><strong>{{ $company['tradeName'] }}</strong><small>{{ $company['name'] }}</small></td>
                                <td>{{ $company['cnpj'] }}</td>
                                <td>{{ $categoryLabels[$company['category']] }}</td>
                                <td><x-ui.badge :variant="$company['status'] === 'ativo' ? 'success' : 'neutral'">{{ $company['status'] === 'ativo' ? 'Ativa' : 'Inativa' }}</x-ui.badge></td>
                                <td class="numeric">{{ count($company['providers']) }}</td>
                                <td>{{ $company['updated'] }}</td>
                                <td><div class="company-row-actions"><x-ui.button variant="secondary" size="sm" wire:click="openCompany('{{ $company['id'] }}')">Visualizar</x-ui.button><x-ui.button variant="ghost" size="sm" wire:click="editCompany('{{ $company['id'] }}')">Editar</x-ui.button></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="company-mobile-list">
                        @foreach ($filteredCompanies as $company)
                            <li>
                                <header><div><strong>{{ $company['tradeName'] }}</strong><small>{{ $company['cnpj'] }}</small></div><x-ui.badge :variant="$company['status'] === 'ativo' ? 'success' : 'neutral'">{{ $company['status'] === 'ativo' ? 'Ativa' : 'Inativa' }}</x-ui.badge></header>
                                <p>{{ $company['name'] }} · {{ $categoryLabels[$company['category']] }}</p>
                                <dl><div><dt>Prestadores</dt><dd>{{ count($company['providers']) }}</dd></div><div><dt>Documentos</dt><dd>{{ count($company['documents']) }}</dd></div></dl>
                                @if ($company['alert'])<x-ui.alert variant="warning">{{ $company['alert'] }}</x-ui.alert>@endif
                                <footer><x-ui.button variant="secondary" size="sm" wire:click="openCompany('{{ $company['id'] }}')">Visualizar</x-ui.button><x-ui.button variant="ghost" size="sm" wire:click="editCompany('{{ $company['id'] }}')">Editar</x-ui.button></footer>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>

            <footer class="company-list-footer"><span>Exibindo {{ count($filteredCompanies) }} de {{ $totalCompanies }} empresas</span></footer>
        </section>
    @elseif ($mode === 'detail' && $selectedCompany)
        @php
            $categoryLabels = ['manutencao' => 'Manutenção', 'limpeza' => 'Limpeza', 'seguranca' => 'Segurança', 'jardinagem' => 'Jardinagem', 'entregas' => 'Entregas', 'outro' => 'Outro'];
            $documentStateLabels = ['nao_enviado' => 'Não enviado', 'enviado' => 'Enviado', 'validado' => 'Validado', 'vencendo' => 'Vencendo', 'expirado' => 'Expirado'];
        @endphp

        <nav class="company-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Empresas e prestadores</button><x-icon name="chevron-right" /><span aria-current="page">{{ $selectedCompany['tradeName'] }}</span></nav>

        <section class="company-detail-hero">
            <div><span>Empresa</span><h2>{{ $selectedCompany['tradeName'] }}</h2><p>{{ $selectedCompany['name'] }} · {{ $selectedCompany['cnpj'] }} · {{ $categoryLabels[$selectedCompany['category']] }}</p></div>
            <div class="company-detail-hero__actions">
                <x-ui.badge :variant="$selectedCompany['status'] === 'ativo' ? 'success' : 'neutral'">{{ $selectedCompany['status'] === 'ativo' ? 'Ativa' : 'Inativa' }}</x-ui.badge>
                <x-ui.button variant="secondary" wire:click="editCompany('{{ $selectedCompany['id'] }}')">Editar dados</x-ui.button>
            </div>
        </section>

        @if ($selectedCompany['alert'])
            <x-ui.alert variant="warning" title="Atenção necessária">{{ $selectedCompany['alert'] }}</x-ui.alert>
        @endif

        <section class="company-detail-metrics" aria-label="Resumo da empresa">
            <article><span>Prestadores vinculados</span><strong>{{ count($selectedCompany['providers']) }}</strong><small>Cadastro próprio na P10</small></article>
            <article><span>Documentos</span><strong>{{ count($selectedCompany['documents']) }}</strong><small>Conforme categoria (RN-031)</small></article>
            <article><span>Serviços autorizados</span><strong>{{ count($selectedCompany['services']) }}</strong><small>Autorização por atividade (RN-030)</small></article>
            <article><span>Última atualização</span><strong>{{ $selectedCompany['updated'] }}</strong><small>Operador: Tatiane Souza</small></article>
        </section>

        <section class="company-detail-grid">
            <article class="company-section-card company-section-card--wide">
                <header><div><h3>Prestadores vinculados</h3><p>Cada prestador mantém cadastro, vigência e situação próprios.</p></div><x-ui.button variant="secondary" size="sm" disabled title="Cadastro de pessoas é feito na P10, com tipo de acesso Prestador">Vincular prestador</x-ui.button></header>
                @if (count($selectedCompany['providers']))
                    <div class="company-provider-list">
                        @foreach ($selectedCompany['providers'] as $provider)
                            <article>
                                <span class="company-provider-avatar">{{ collect(explode(' ', $provider['name']))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('') }}</span>
                                <div><strong>{{ $provider['name'] }}</strong><small>{{ $provider['document'] }}</small></div>
                                <dl><div><dt>Atividade</dt><dd>{{ $provider['role'] }}</dd></div><div><dt>Vigência</dt><dd>{{ $provider['validity'] }}</dd></div></dl>
                                <x-ui.badge :variant="$provider['status'] === 'ativo' ? 'success' : 'neutral'">{{ $provider['status'] === 'ativo' ? 'Vínculo ativo' : 'Vínculo encerrado' }}</x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" disabled title="Encerramento será conectado ao cadastro de pessoas">Gerir vínculo</x-ui.button>
                            </article>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state title="Nenhum prestador vinculado" description="Cadastre uma pessoa com tipo de acesso Prestador e vincule a esta empresa." />
                @endif
            </article>

            <article class="company-section-card">
                <header><div><h3>Documentos da empresa</h3><p>Conforme a categoria de atividade (RN-031).</p></div></header>
                @if (count($selectedCompany['documents']))
                    <ul class="company-document-list">
                        @foreach ($selectedCompany['documents'] as $document)
                            <li><span>{{ $document['label'] }}</span><x-ui.badge :variant="match ($document['state']) { 'validado' => 'success', 'enviado' => 'info', 'vencendo' => 'warning', 'expirado' => 'danger', default => 'neutral' }">{{ $documentStateLabels[$document['state']] }}</x-ui.badge></li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="Nenhum documento enviado" description="Documentos exigidos variam conforme a categoria da empresa." />
                @endif
            </article>

            <article class="company-section-card">
                <header><div><h3>Serviços autorizados</h3><p>Autorização separada por atividade (RN-030).</p></div></header>
                @if (count($selectedCompany['services']))
                    <ul class="company-service-list">
                        @foreach ($selectedCompany['services'] as $service)
                            <li><span>{{ $service['label'] }}</span><x-ui.badge :variant="$service['status'] === 'autorizado' ? 'success' : 'danger'">{{ $service['status'] === 'autorizado' ? 'Autorizado' : 'Suspenso' }}</x-ui.badge></li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state title="Nenhum serviço autorizado" description="Autorize serviços por atividade após validar a documentação." />
                @endif
            </article>

            <article class="company-section-card company-section-card--wide">
                <header><div><h3>Histórico da empresa</h3><p>Eventos não são apagados pelo usuário operacional.</p></div></header>
                <ul class="company-history-list"><li><span></span><div><strong>Cadastro consultado</strong><small>10/08/2026 às 19:00 · Tatiane Souza</small></div></li><li><span></span><div><strong>Dados da empresa revisados</strong><small>{{ $selectedCompany['updated'] }} · Administração</small></div></li><li><span></span><div><strong>Empresa cadastrada</strong><small>02/03/2024 · Implantação Santa Rita</small></div></li></ul>
            </article>
        </section>

        <section class="company-status-actions">
            <div><h3>Situação da empresa</h3><p>Inativar a empresa não apaga prestadores, documentos ou histórico (RN-033).</p></div>
            <x-ui.modal id="company-status-modal" title="Alterar situação da empresa" description="Confirme o efeito desta alteração." :trigger-label="$selectedCompany['status'] === 'inativo' ? 'Reativar empresa' : 'Inativar empresa'" :trigger-variant="$selectedCompany['status'] === 'inativo' ? 'success' : 'danger'">
                <x-ui.alert variant="warning" title="Vínculos serão preservados">A situação da empresa mudará, mas cada prestador e documento continuará com seu próprio estado. Inativar impede novas autorizações.</x-ui.alert>
                <x-slot:confirm><form method="dialog"><x-ui.button type="submit" :variant="$selectedCompany['status'] === 'inativo' ? 'success' : 'danger'" wire:click="toggleCompanyStatus">Confirmar alteração</x-ui.button></form></x-slot:confirm>
            </x-ui.modal>
        </section>
    @else
        <nav class="company-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Empresas e prestadores</button><x-icon name="chevron-right" /><span aria-current="page">{{ $editingCompanyId ? 'Editar empresa' : 'Nova empresa' }}</span></nav>

        <section class="company-form-layout">
            <form class="company-form-card" wire:submit="saveCompany">
                <header><div><h2>{{ $editingCompanyId ? 'Editar empresa' : 'Cadastrar empresa' }}</h2><p>Cadastre primeiro a empresa. Prestadores serão vinculados separadamente pela P10.</p></div><x-ui.badge :variant="$companyStatus === 'ativo' ? 'success' : 'neutral'">{{ $companyStatus === 'ativo' ? 'Ativa' : 'Inativa' }}</x-ui.badge></header>

                <fieldset>
                    <legend>Identificação</legend>
                    <div class="company-form-fields">
                        <x-ui.field id="company-cnpj" label="CNPJ" wire:model="cnpj" placeholder="00.000.000/0000-00" :error="$errors->first('cnpj')" required />
                        <x-ui.field id="company-name" label="Razão social" wire:model="name" :error="$errors->first('name')" required />
                        <x-ui.field id="company-trade-name" label="Nome fantasia" wire:model="tradeName" :error="$errors->first('tradeName')" />
                        <x-ui.select id="company-category" label="Categoria de atividade" wire:model="category" :error="$errors->first('category')" required>
                            <option value="manutencao">Manutenção</option>
                            <option value="limpeza">Limpeza</option>
                            <option value="seguranca">Segurança</option>
                            <option value="jardinagem">Jardinagem</option>
                            <option value="entregas">Entregas</option>
                            <option value="outro">Outro</option>
                        </x-ui.select>
                        <x-ui.select id="company-form-status" label="Situação da empresa" wire:model.live="companyStatus" :error="$errors->first('companyStatus')" required>
                            <option value="ativo">Ativa</option>
                            <option value="inativo">Inativa</option>
                        </x-ui.select>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Contato</legend>
                    <div class="company-form-fields">
                        <x-ui.field id="company-phone" label="Telefone" wire:model="phone" :error="$errors->first('phone')" required />
                        <x-ui.field id="company-email" label="E-mail" type="email" wire:model="email" :error="$errors->first('email')" required />
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Observações</legend>
                    <label class="company-notes-field" for="company-notes">
                        <span>Observações</span>
                        <textarea id="company-notes" wire:model="notes" maxlength="300" rows="4" placeholder="Registre somente informações relevantes sobre a empresa…"></textarea>
                        <small>{{ mb_strlen($notes) }}/300 caracteres</small>
                    </label>
                </fieldset>

                <footer><x-ui.button variant="secondary" wire:click="backToList">Cancelar</x-ui.button><x-ui.button variant="warning" wire:click="saveDraft">Salvar rascunho</x-ui.button><x-ui.button type="submit" variant="success">Salvar empresa</x-ui.button></footer>
            </form>

            <aside class="company-form-context">
                <x-ui.card title="O que acontece ao salvar?" description="Cada entidade mantém seu próprio estado">
                    <ul>
                        <li><x-icon name="building" /><span><strong>Empresa</strong><small>Os dados cadastrais são registrados.</small></span></li>
                        <li><x-icon name="users-round" /><span><strong>Prestadores</strong><small>Nenhum prestador é criado automaticamente.</small></span></li>
                        <li><x-icon name="badge-check" /><span><strong>Autorizações</strong><small>Nenhum serviço é autorizado automaticamente.</small></span></li>
                    </ul>
                </x-ui.card>
                <x-ui.alert variant="info" title="Cadastro empresarial">Prestadores, documentos e autorizações são cadastrados em fluxos próprios e não são criados automaticamente aqui.</x-ui.alert>
            </aside>
        </section>
    @endif
</div>
