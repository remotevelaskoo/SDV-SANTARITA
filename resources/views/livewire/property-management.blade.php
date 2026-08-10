<div class="property-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        @php
            $propertyCounts = [
                'active' => collect($properties)->where('status', 'ativo')->count(),
                'implementation' => collect($properties)->where('status', 'implantacao')->count(),
                'blocked' => collect($properties)->where('status', 'bloqueado')->count(),
                'occupants' => collect($properties)->sum('occupants'),
            ];
        @endphp

        <section class="property-summary-grid" aria-label="Resumo dos imóveis">
            <article><span>Imóveis ativos</span><strong>{{ $propertyCounts['active'] }}</strong><small>Estrutura disponível</small></article>
            <article><span>Em implantação</span><strong>{{ $propertyCounts['implementation'] }}</strong><small>Cadastros incompletos</small></article>
            <article><span>Bloqueados</span><strong>{{ $propertyCounts['blocked'] }}</strong><small>Requerem atenção</small></article>
            <article><span>Ocupantes ativos</span><strong>{{ $propertyCounts['occupants'] }}</strong><small>Vínculos individuais</small></article>
        </section>

        <section class="property-list-card" aria-labelledby="property-list-title">
            <header>
                <div><h2 id="property-list-title">Cadastro de imóveis</h2><p>Consulte a estrutura, a ocupação e os responsáveis.</p></div>
                <x-ui.button variant="primary" wire:click="createProperty">Cadastrar imóvel</x-ui.button>
            </header>

            <div class="property-list-filters">
                <label class="property-search">
                    <span class="sr-only">Buscar imóveis</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar código, bloco, unidade, endereço ou responsável">
                </label>
                <x-ui.select id="property-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todos">Todas as situações</option>
                    <option value="ativo">Ativos</option>
                    <option value="implantacao">Em implantação</option>
                    <option value="inativo">Inativos</option>
                    <option value="bloqueado">Bloqueados</option>
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Lista de imóveis"
                :state="count($filteredProperties) ? 'ready' : 'empty'"
                empty-title="Nenhum imóvel encontrado"
                empty-description="Revise a busca ou selecione outra situação."
            >
                <x-slot:table>
                    <thead><tr><th>Imóvel</th><th>Endereço</th><th>Situação</th><th>Responsável</th><th>Ocupantes</th><th>Veículos</th><th>Atualização</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredProperties as $property)
                            <tr>
                                <td><strong>{{ $property['code'] }}</strong><small>Bloco {{ $property['block'] ?: '—' }} · Unidade {{ $property['unit'] }}</small></td>
                                <td>{{ $property['address'] }}</td>
                                <td><x-ui.badge :variant="match ($property['status']) { 'ativo' => 'success', 'bloqueado' => 'danger', 'implantacao' => 'warning', default => 'neutral' }">{{ match ($property['status']) { 'ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'implantacao' => 'Em implantação', default => 'Inativo' } }}</x-ui.badge></td>
                                <td>{{ $property['responsible'] }}</td>
                                <td class="numeric">{{ $property['occupants'] }}</td>
                                <td class="numeric">{{ $property['vehicles'] }}</td>
                                <td>{{ $property['updated'] }}</td>
                                <td><div class="property-row-actions"><x-ui.button variant="secondary" size="sm" wire:click="openProperty({{ $property['id'] }})">Visualizar</x-ui.button><x-ui.button variant="ghost" size="sm" wire:click="editProperty({{ $property['id'] }})">Editar</x-ui.button></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="property-mobile-list">
                        @foreach ($filteredProperties as $property)
                            <li>
                                <header><div><strong>{{ $property['code'] }}</strong><small>Bloco {{ $property['block'] ?: '—' }} · Unidade {{ $property['unit'] }}</small></div><x-ui.badge :variant="match ($property['status']) { 'ativo' => 'success', 'bloqueado' => 'danger', 'implantacao' => 'warning', default => 'neutral' }">{{ match ($property['status']) { 'ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'implantacao' => 'Implantação', default => 'Inativo' } }}</x-ui.badge></header>
                                <p>{{ $property['address'] }}</p>
                                <dl><div><dt>Responsável</dt><dd>{{ $property['responsible'] }}</dd></div><div><dt>Ocupantes</dt><dd>{{ $property['occupants'] }}</dd></div><div><dt>Veículos</dt><dd>{{ $property['vehicles'] }}</dd></div></dl>
                                @if ($property['alert'])<x-ui.alert variant="warning">{{ $property['alert'] }}</x-ui.alert>@endif
                                <footer><x-ui.button variant="secondary" size="sm" wire:click="openProperty({{ $property['id'] }})">Visualizar</x-ui.button><x-ui.button variant="ghost" size="sm" wire:click="editProperty({{ $property['id'] }})">Editar</x-ui.button></footer>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>

            <footer class="property-list-footer"><span>Exibindo {{ count($filteredProperties) }} de {{ count($properties) }} imóveis</span><small>Dados demonstrativos da P11</small></footer>
        </section>
    @elseif ($mode === 'detail' && $selectedProperty)
        <nav class="property-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Imóveis</button><x-icon name="chevron-right" /><span aria-current="page">{{ $selectedProperty['code'] }}</span></nav>

        <section class="property-detail-hero">
            <div><span>Imóvel</span><h2>{{ $selectedProperty['code'] }}</h2><p>Bloco {{ $selectedProperty['block'] ?: '—' }} · Unidade {{ $selectedProperty['unit'] }} · {{ $selectedProperty['address'] }}</p></div>
            <div class="property-detail-hero__actions">
                <x-ui.badge :variant="match ($selectedProperty['status']) { 'ativo' => 'success', 'bloqueado' => 'danger', 'implantacao' => 'warning', default => 'neutral' }">{{ match ($selectedProperty['status']) { 'ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'implantacao' => 'Em implantação', default => 'Inativo' } }}</x-ui.badge>
                <x-ui.button variant="secondary" wire:click="editProperty({{ $selectedProperty['id'] }})">Editar dados</x-ui.button>
            </div>
        </section>

        @if ($selectedProperty['alert'])
            <x-ui.alert variant="warning" title="Atenção necessária">{{ $selectedProperty['alert'] }}</x-ui.alert>
        @endif

        <section class="property-detail-metrics" aria-label="Resumo do imóvel">
            <article><span>Responsável principal</span><strong>{{ $selectedProperty['responsible'] }}</strong><small>Responsabilidade separada do acesso</small></article>
            <article><span>Vínculos ativos</span><strong>{{ count($selectedProperty['links']) }}</strong><small>Pessoas com cadastro próprio</small></article>
            <article><span>Veículos ativos</span><strong>{{ count($selectedProperty['vehicleList']) }}</strong><small>Vínculos próprios e rastreáveis</small></article>
            <article><span>Última atualização</span><strong>{{ $selectedProperty['updated'] }}</strong><small>Operador: Tatiane Souza</small></article>
        </section>

        <section class="property-detail-grid">
            <article class="property-section-card property-section-card--wide">
                <header><div><h3>Pessoas e vínculos</h3><p>Natureza, papel e responsabilidade permanecem separados.</p></div><x-ui.button variant="secondary" size="sm" disabled title="Cadastro de pessoas será implementado na P10">Adicionar vínculo</x-ui.button></header>
                @if (count($selectedProperty['links']))
                    <div class="property-links-list">
                        @foreach ($selectedProperty['links'] as $link)
                            <article>
                                <span class="property-link-avatar">{{ collect(explode(' ', $link['name']))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('') }}</span>
                                <div><strong>{{ $link['name'] }}</strong><small>{{ $link['document'] }}</small></div>
                                <dl><div><dt>Natureza</dt><dd>{{ $link['nature'] }}</dd></div><div><dt>Papel</dt><dd>{{ $link['role'] }}</dd></div><div><dt>Responsabilidade</dt><dd>{{ $link['responsibility'] }}</dd></div><div><dt>Vigência</dt><dd>{{ $link['validity'] }}</dd></div></dl>
                                <x-ui.badge variant="success">Vínculo ativo</x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" disabled title="Encerramento será conectado ao cadastro de pessoas">Gerir vínculo</x-ui.button>
                            </article>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state title="Nenhuma pessoa vinculada" description="Cadastre ou selecione uma pessoa para criar um vínculo independente com este imóvel." />
                @endif
            </article>

            <article class="property-section-card">
                <header><div><h3>Dados estruturais</h3><p>O endereço pertence ao imóvel.</p></div></header>
                <dl class="property-structural-data"><div><dt>Condomínio</dt><dd>Santa Rita</dd></div><div><dt>Bloco</dt><dd>{{ $selectedProperty['block'] ?: 'Não utilizado' }}</dd></div><div><dt>Unidade</dt><dd>{{ $selectedProperty['unit'] }}</dd></div><div><dt>Código</dt><dd>{{ $selectedProperty['code'] }}</dd></div><div><dt>Endereço</dt><dd>{{ $selectedProperty['address'] }}</dd></div></dl>
                <x-ui.alert variant="info">Alterar o endereço impacta a apresentação de todos os vínculos residenciais e deve gerar auditoria.</x-ui.alert>
            </article>

            <article class="property-section-card">
                <header><div><h3>Veículos vinculados</h3><p>Veículo, pessoa e imóvel mantêm situações próprias.</p></div><x-ui.button variant="secondary" size="sm" disabled title="Cadastro de veículos será implementado na P12">Adicionar veículo</x-ui.button></header>
                @if (count($selectedProperty['vehicleList']))
                    <div class="property-vehicle-list">
                        @foreach ($selectedProperty['vehicleList'] as $vehicle)
                            <article><span><x-icon name="car" /></span><div><strong>{{ $vehicle['plate'] }}</strong><small>{{ $vehicle['model'] }} · {{ $vehicle['color'] }}</small><p>{{ $vehicle['owner'] }} · {{ $vehicle['link'] }}</p></div><x-ui.badge variant="success">Ativo</x-ui.badge></article>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state title="Nenhum veículo vinculado" description="O imóvel pode existir e permanecer ativo sem veículos cadastrados." />
                @endif
            </article>

            <article class="property-section-card property-section-card--wide">
                <header><div><h3>Histórico do imóvel</h3><p>Eventos estruturais não são apagados pelo usuário operacional.</p></div></header>
                <ul class="property-history-list"><li><span></span><div><strong>Cadastro consultado</strong><small>10/08/2026 às 18:42 · Tatiane Souza</small></div></li><li><span></span><div><strong>Dados estruturais revisados</strong><small>{{ $selectedProperty['updated'] }} · Administração</small></div></li><li><span></span><div><strong>Imóvel criado</strong><small>15/05/2022 · Implantação Santa Rita</small></div></li></ul>
            </article>
        </section>

        <section class="property-status-actions">
            <div><h3>Situação estrutural</h3><p>Bloquear o imóvel não apaga moradores, veículos ou histórico.</p></div>
            <x-ui.modal id="property-status-modal" title="Alterar situação do imóvel" description="Confirme o efeito desta alteração." :trigger-label="$selectedProperty['status'] === 'bloqueado' ? 'Reativar imóvel' : 'Bloquear imóvel'" :trigger-variant="$selectedProperty['status'] === 'bloqueado' ? 'success' : 'danger'">
                <x-ui.alert variant="warning" title="Vínculos serão preservados">A situação estrutural mudará, mas cada pessoa e autorização continuará com seu próprio estado.</x-ui.alert>
                <x-slot:confirm><form method="dialog"><x-ui.button type="submit" :variant="$selectedProperty['status'] === 'bloqueado' ? 'success' : 'danger'" wire:click="togglePropertyBlock">Confirmar alteração</x-ui.button></form></x-slot:confirm>
            </x-ui.modal>
        </section>
    @else
        <nav class="property-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Imóveis</button><x-icon name="chevron-right" /><span aria-current="page">{{ $editingPropertyId ? 'Editar imóvel' : 'Novo imóvel' }}</span></nav>

        <section class="property-form-layout">
            <form class="property-form-card" wire:submit="saveProperty">
                <header><div><h2>{{ $editingPropertyId ? 'Editar imóvel' : 'Cadastrar imóvel' }}</h2><p>Cadastre primeiro a estrutura. Pessoas e acessos serão vinculados separadamente.</p></div><x-ui.badge :variant="$propertyStatus === 'ativo' ? 'success' : 'warning'">{{ $propertyStatus === 'ativo' ? 'Ativo' : 'Em implantação' }}</x-ui.badge></header>

                <fieldset><legend>Identificação</legend><div class="property-form-fields"><x-ui.field id="property-organization" label="Condomínio ou organização" wire:model="organization" :error="$errors->first('organization')" required /><x-ui.field id="property-block" label="Bloco" wire:model="block" help="Opcional quando a implantação não utiliza blocos." /><x-ui.field id="property-unit" label="Unidade" wire:model="unit" :error="$errors->first('unit')" required /><x-ui.field id="property-code" label="Código único" wire:model="code" placeholder="SRA-A-102" :error="$errors->first('code')" required /><x-ui.select id="property-form-status" label="Situação do imóvel" wire:model.live="propertyStatus" :error="$errors->first('propertyStatus')" required><option value="implantacao">Em implantação</option><option value="ativo">Ativo</option><option value="inativo">Inativo</option><option value="bloqueado">Bloqueado</option></x-ui.select></div></fieldset>

                <fieldset>
                    <legend>Endereço estrutural</legend>
                    <x-ui.alert variant="info" title="Endereço compartilhado">Este endereço pertence ao imóvel e será apresentado aos vínculos residenciais. Ele não será copiado para cada pessoa.</x-ui.alert>
                    <p class="property-zip-hint">
                        <span wire:loading wire:target="lookupZipCode" class="ui-loading"><span class="ui-spinner" aria-hidden="true"></span> Buscando endereço pelo CEP…</span>
                        <span wire:loading.remove wire:target="lookupZipCode">Ao sair do campo CEP, preenchemos logradouro, bairro, cidade e estado automaticamente.</span>
                    </p>
                    @if ($zipCodeLookupFailed)
                        <x-ui.alert variant="warning" title="CEP não encontrado">Não localizamos este CEP automaticamente. Preencha o endereço manualmente.</x-ui.alert>
                    @endif
                    <div class="property-form-fields property-form-fields--address">
                        <x-ui.field id="property-zip" label="CEP" wire:model="zipCode" wire:blur="lookupZipCode" placeholder="00000-000" :error="$errors->first('zipCode')" required />
                        <x-ui.field id="property-street" label="Logradouro" wire:model="street" :error="$errors->first('street')" required />
                        <x-ui.field id="property-number" label="Número" wire:model="number" :error="$errors->first('number')" required />
                        <x-ui.field id="property-complement" label="Complemento" wire:model="complement" />
                        <x-ui.field id="property-district" label="Bairro" wire:model="district" :error="$errors->first('district')" required />
                        <x-ui.field id="property-city" label="Cidade" wire:model="city" :error="$errors->first('city')" required />
                        <x-ui.field id="property-state" label="Estado" wire:model="state" :error="$errors->first('state')" required />
                    </div>
                </fieldset>

                <fieldset><legend>Observações estruturais</legend><label class="property-notes-field" for="property-notes"><span>Observações</span><textarea id="property-notes" wire:model="notes" maxlength="300" rows="4" placeholder="Registre somente informações estruturais do imóvel…"></textarea><small>{{ mb_strlen($notes) }}/300 caracteres</small></label></fieldset>

                <footer><x-ui.button variant="secondary" wire:click="backToList">Cancelar</x-ui.button><x-ui.button variant="warning" wire:click="saveDraft">Salvar rascunho</x-ui.button><x-ui.button type="submit" variant="success">Salvar imóvel</x-ui.button></footer>
            </form>

            <aside class="property-form-context">
                <x-ui.card title="O que acontece ao salvar?" description="Cada entidade mantém seu próprio estado">
                    <ul><li><x-icon name="building" /><span><strong>Imóvel</strong><small>A estrutura e o endereço são registrados.</small></span></li><li><x-icon name="users" /><span><strong>Pessoas</strong><small>Nenhuma pessoa é criada automaticamente.</small></span></li><li><x-icon name="key" /><span><strong>Acessos</strong><small>Nenhuma autorização ou credencial é ativada.</small></span></li><li><x-icon name="car" /><span><strong>Veículos</strong><small>Serão ligados por fluxo próprio na P12.</small></span></li></ul>
                </x-ui.card>
                <x-ui.alert variant="warning" title="Protótipo demonstrativo">Os dados ficam somente na memória desta tela e não são gravados em banco de dados nesta etapa.</x-ui.alert>
            </aside>
        </section>
    @endif
</div>
