<div class="vehicle-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        <section class="vehicle-summary-grid" aria-label="Resumo dos veículos">
            <article><span>Veículos ativos</span><strong>{{ $vehicleCounts['active'] }}</strong><small>Cadastros disponíveis</small></article>
            <article><span>Aguardando análise</span><strong>{{ $vehicleCounts['pending'] }}</strong><small>Sem liberação automática</small></article>
            <article><span>Bloqueados</span><strong>{{ $vehicleCounts['blocked'] }}</strong><small>Requerem atenção</small></article>
            <article><span>Placas sincronizadas</span><strong>{{ $vehicleCounts['synced'] }}</strong><small>Leitura demonstrativa</small></article>
        </section>

        <section class="vehicle-list-card" aria-labelledby="vehicle-list-title">
            <header>
                <div><h2 id="vehicle-list-title">Cadastro de veículos</h2><p>Consulte placas, características, proprietários e vínculos.</p></div>
                <x-ui.button variant="primary" wire:click="createVehicle">Cadastrar veículo</x-ui.button>
            </header>

            <div class="vehicle-list-filters">
                <label class="vehicle-search">
                    <span class="sr-only">Buscar veículos</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar placa, marca, modelo, proprietário ou imóvel">
                </label>
                <x-ui.select id="vehicle-type-filter" label="Tipo" wire:model.live="typeFilter">
                    <option value="todos">Todos os tipos</option>
                    <option value="carro">Carros</option>
                    <option value="moto">Motos</option>
                    <option value="utilitario">Utilitários</option>
                    <option value="caminhao">Caminhões</option>
                    <option value="outro">Outros</option>
                </x-ui.select>
                <x-ui.select id="vehicle-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todos">Todas as situações</option>
                    <option value="ativo">Ativos</option>
                    <option value="pendente">Pendentes</option>
                    <option value="inativo">Inativos</option>
                    <option value="bloqueado">Bloqueados</option>
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Lista de veículos"
                :state="count($filteredVehicles) ? 'ready' : 'empty'"
                empty-title="Nenhum veículo encontrado"
                empty-description="Revise a busca ou selecione outros filtros."
            >
                <x-slot:table>
                    <thead><tr><th>Placa / veículo</th><th>Proprietário</th><th>Vínculo</th><th>Situação</th><th>Leitura de placa</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredVehicles as $vehicle)
                            <tr>
                                <td><strong>{{ $vehicle['plate'] }}</strong><small>{{ $vehicle['brand'] }} {{ $vehicle['model'] }} · {{ $vehicle['color'] }}</small></td>
                                <td>{{ $vehicle['owner'] }}</td>
                                <td><strong>{{ $vehicle['propertyCode'] }}</strong><small>{{ $vehicle['relationship'] }}</small></td>
                                <td><x-ui.badge :variant="match ($vehicle['status']) { 'ativo' => 'success', 'bloqueado' => 'danger', 'pendente' => 'warning', default => 'neutral' }">{{ match ($vehicle['status']) { 'ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'pendente' => 'Pendente', default => 'Inativo' } }}</x-ui.badge></td>
                                <td><x-ui.badge :variant="match ($vehicle['lprStatus']) { 'sincronizado' => 'success', 'revisao' => 'warning', 'suspenso' => 'danger', default => 'neutral' }">{{ match ($vehicle['lprStatus']) { 'sincronizado' => 'Sincronizada', 'revisao' => 'Em revisão', 'suspenso' => 'Suspensa', default => 'Não sincronizada' } }}</x-ui.badge></td>
                                <td><div class="vehicle-row-actions"><x-ui.button variant="secondary" size="sm" wire:click="openVehicle('{{ $vehicle['id'] }}')">Visualizar</x-ui.button><x-ui.button variant="ghost" size="sm" wire:click="editVehicle('{{ $vehicle['id'] }}')">Editar</x-ui.button></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="vehicle-mobile-list">
                        @foreach ($filteredVehicles as $vehicle)
                            <li>
                                <header><div><strong>{{ $vehicle['plate'] }}</strong><small>{{ $vehicle['brand'] }} {{ $vehicle['model'] }}</small></div><x-ui.badge :variant="match ($vehicle['status']) { 'ativo' => 'success', 'bloqueado' => 'danger', 'pendente' => 'warning', default => 'neutral' }">{{ match ($vehicle['status']) { 'ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'pendente' => 'Pendente', default => 'Inativo' } }}</x-ui.badge></header>
                                <dl><div><dt>Proprietário</dt><dd>{{ $vehicle['owner'] }}</dd></div><div><dt>Imóvel</dt><dd>{{ $vehicle['propertyCode'] }}</dd></div><div><dt>Características</dt><dd>{{ $vehicle['color'] }} · {{ $vehicle['year'] }}</dd></div><div><dt>Leitura de placa</dt><dd>{{ match ($vehicle['lprStatus']) { 'sincronizado' => 'Sincronizada', 'revisao' => 'Em revisão', 'suspenso' => 'Suspensa', default => 'Não sincronizada' } }}</dd></div></dl>
                                @if ($vehicle['alert'])<x-ui.alert variant="warning">{{ $vehicle['alert'] }}</x-ui.alert>@endif
                                <footer><x-ui.button variant="secondary" size="sm" wire:click="openVehicle('{{ $vehicle['id'] }}')">Visualizar</x-ui.button><x-ui.button variant="ghost" size="sm" wire:click="editVehicle('{{ $vehicle['id'] }}')">Editar</x-ui.button></footer>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>

            <footer class="vehicle-list-footer"><span>Exibindo {{ count($filteredVehicles) }} de {{ $totalVehicles }} veículos</span></footer>
        </section>
    @elseif ($mode === 'detail' && $selectedVehicle)
        <nav class="vehicle-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Veículos</button><x-icon name="chevron-right" /><span aria-current="page">{{ $selectedVehicle['plate'] }}</span></nav>

        <section class="vehicle-detail-hero">
            <span class="vehicle-detail-icon"><x-icon name="car" /></span>
            <div><span>Placa do veículo</span><h2>{{ $selectedVehicle['plate'] }}</h2><p>{{ $selectedVehicle['brand'] }} {{ $selectedVehicle['model'] }} · {{ $selectedVehicle['color'] }} · {{ $selectedVehicle['year'] }}</p></div>
            <div class="vehicle-detail-hero__actions"><x-ui.badge :variant="match ($selectedVehicle['status']) { 'ativo' => 'success', 'bloqueado' => 'danger', 'pendente' => 'warning', default => 'neutral' }">{{ match ($selectedVehicle['status']) { 'ativo' => 'Ativo', 'bloqueado' => 'Bloqueado', 'pendente' => 'Pendente', default => 'Inativo' } }}</x-ui.badge><x-ui.button variant="secondary" wire:click="editVehicle('{{ $selectedVehicle['id'] }}')">Editar dados</x-ui.button></div>
        </section>

        @if ($selectedVehicle['alert'])<x-ui.alert variant="warning" title="Atenção necessária">{{ $selectedVehicle['alert'] }}</x-ui.alert>@endif

        <section class="vehicle-detail-metrics" aria-label="Resumo do veículo">
            <article><span>Tipo de uso</span><strong>{{ match ($selectedVehicle['accessUse']) { 'morador' => 'Morador', 'visitante' => 'Visitante', 'prestador' => 'Prestador', default => 'Administrativo' } }}</strong><small>Classificação operacional</small></article>
            <article><span>Vínculo principal</span><strong>{{ $selectedVehicle['propertyCode'] }}</strong><small>{{ $selectedVehicle['relationship'] }}</small></article>
            <article><span>Leitura de placa</span><strong>{{ match ($selectedVehicle['lprStatus']) { 'sincronizado' => 'Sincronizada', 'revisao' => 'Em revisão', 'suspenso' => 'Suspensa', default => 'Não sincronizada' } }}</strong><small>Estado demonstrativo</small></article>
            <article><span>Última atualização</span><strong>{{ $selectedVehicle['updated'] }}</strong><small>Cadastro do veículo</small></article>
        </section>

        <section class="vehicle-detail-grid">
            <article class="vehicle-section-card">
                <header><div><h3>Dados do veículo</h3><p>Características usadas na conferência visual.</p></div></header>
                <dl class="vehicle-data-list"><div><dt>Placa</dt><dd>{{ $selectedVehicle['plate'] }}</dd></div><div><dt>Tipo</dt><dd>{{ ucfirst($selectedVehicle['type']) }}</dd></div><div><dt>Marca / modelo</dt><dd>{{ $selectedVehicle['brand'] }} {{ $selectedVehicle['model'] }}</dd></div><div><dt>Cor</dt><dd>{{ $selectedVehicle['color'] }}</dd></div><div><dt>Ano</dt><dd>{{ $selectedVehicle['year'] }}</dd></div><div><dt>RENAVAM</dt><dd>{{ $selectedVehicle['renavam'] }}</dd></div></dl>
                <x-ui.alert variant="info">O documento aparece protegido. A conferência completa dependerá de permissão específica no sistema real.</x-ui.alert>
            </article>

            <article class="vehicle-section-card">
                <header><div><h3>Proprietário e vínculo</h3><p>As entidades mantêm situações independentes.</p></div></header>
                <div class="vehicle-owner-card"><span>{{ collect(explode(' ', $selectedVehicle['owner']))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('') }}</span><div><strong>{{ $selectedVehicle['owner'] }}</strong><small>{{ $selectedVehicle['ownerDocument'] }}</small></div><x-ui.badge variant="success">Cadastro localizado</x-ui.badge></div>
                <dl class="vehicle-data-list"><div><dt>Imóvel</dt><dd>{{ $selectedVehicle['propertyCode'] }}</dd></div><div><dt>Natureza do vínculo</dt><dd>{{ $selectedVehicle['relationship'] }}</dd></div><div><dt>Uso informado</dt><dd>{{ ucfirst($selectedVehicle['accessUse']) }}</dd></div></dl>
                <x-ui.alert variant="warning">Cadastrar ou bloquear este veículo não altera a situação da pessoa nem do imóvel vinculado.</x-ui.alert>
            </article>

            <article class="vehicle-section-card">
                <header><div><h3>Leitura automática de placa (LPR)</h3><p>Monitoramento demonstrativo, sem equipamento conectado.</p></div></header>
                <div class="vehicle-lpr-status"><span><x-icon name="car" /></span><div><strong>{{ match ($selectedVehicle['lprStatus']) { 'sincronizado' => 'Placa sincronizada', 'revisao' => 'Requer revisão visual', 'suspenso' => 'Leitura suspensa', default => 'Aguardando sincronização' } }}</strong><small>{{ $selectedVehicle['lastReading'] }}</small></div></div>
                <x-ui.alert variant="info">O reconhecimento da placa ajuda na conferência, mas não libera entrada sozinho.</x-ui.alert>
            </article>

            <article class="vehicle-section-card">
                <header><div><h3>Histórico do veículo</h3><p>Eventos são preservados para futura auditoria.</p></div></header>
                <ul class="vehicle-history-list"><li><span></span><div><strong>Cadastro consultado</strong><small>10/08/2026 às 19:24 · Tatiane Souza</small></div></li><li><span></span><div><strong>Dados revisados</strong><small>{{ $selectedVehicle['updated'] }} · Administração</small></div></li><li><span></span><div><strong>Veículo cadastrado</strong><small>15/05/2022 · Implantação Santa Rita</small></div></li></ul>
            </article>
        </section>

        <section class="vehicle-status-actions">
            <div><h3>Situação do veículo</h3><p>Bloquear preserva proprietário, imóvel, documentos e histórico.</p></div>
            <x-ui.modal id="vehicle-status-modal" title="Alterar situação do veículo" description="Confirme o efeito desta alteração." :trigger-label="$selectedVehicle['status'] === 'bloqueado' ? 'Reativar veículo' : 'Bloquear veículo'" :trigger-variant="$selectedVehicle['status'] === 'bloqueado' ? 'success' : 'danger'">
                <x-ui.alert variant="warning" title="Nenhuma pessoa será bloqueada">Somente a situação deste veículo mudará. A entrada ainda dependerá da validação da portaria.</x-ui.alert>
                <x-slot:confirm><form method="dialog"><x-ui.button type="submit" :variant="$selectedVehicle['status'] === 'bloqueado' ? 'success' : 'danger'" wire:click="toggleVehicleBlock">Confirmar alteração</x-ui.button></form></x-slot:confirm>
            </x-ui.modal>
        </section>
    @else
        <nav class="vehicle-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Veículos</button><x-icon name="chevron-right" /><span aria-current="page">{{ $editingVehicleId ? 'Editar veículo' : 'Novo veículo' }}</span></nav>

        <section class="vehicle-form-layout">
            <form class="vehicle-form-card" wire:submit="saveVehicle">
                <header><div><h2>{{ $editingVehicleId ? 'Editar veículo' : 'Cadastrar veículo' }}</h2><p>Registre o veículo e depois confira os vínculos e a autorização.</p></div><x-ui.badge :variant="$vehicleStatus === 'ativo' ? 'success' : 'warning'">{{ $vehicleStatus === 'ativo' ? 'Ativo' : 'Sem liberação automática' }}</x-ui.badge></header>

                <fieldset><legend>Identificação do veículo</legend><div class="vehicle-form-fields"><x-ui.field id="vehicle-plate" label="Placa" wire:model="plate" placeholder="ABC1D23" help="Aceita o padrão Mercosul ou o modelo antigo." :error="$errors->first('plate')" required /><x-ui.select id="vehicle-type" label="Tipo" wire:model="type" :error="$errors->first('type')" required><option value="carro">Carro</option><option value="moto">Moto</option><option value="utilitario">Utilitário</option><option value="caminhao">Caminhão</option><option value="outro">Outro</option></x-ui.select><x-ui.field id="vehicle-brand" label="Marca" wire:model="brand" :error="$errors->first('brand')" required /><x-ui.field id="vehicle-model" label="Modelo" wire:model="model" :error="$errors->first('model')" required /><x-ui.field id="vehicle-color" label="Cor predominante" wire:model="color" :error="$errors->first('color')" required /><x-ui.field id="vehicle-year" label="Ano" type="number" min="1900" max="2027" wire:model="year" :error="$errors->first('year')" required /><x-ui.field id="vehicle-renavam" label="RENAVAM" inputmode="numeric" wire:model="renavam" help="Opcional no protótipo; será exibido protegido." :error="$errors->first('renavam')" /></div></fieldset>

                <fieldset><legend>Proprietário e vínculo</legend><x-ui.alert variant="info" title="Cadastros separados">O veículo pode ser ligado a uma pessoa e a um imóvel, sem copiar ou alterar esses cadastros.</x-ui.alert><div class="vehicle-form-fields"><x-ui.field id="vehicle-owner" label="Proprietário ou responsável" wire:model="owner" :error="$errors->first('owner')" required /><x-ui.field id="vehicle-owner-document" label="CPF ou CNPJ" wire:model="ownerDocument" help="Opcional nesta demonstração." :error="$errors->first('ownerDocument')" /><x-ui.field id="vehicle-property" label="Código do imóvel" wire:model="propertyCode" placeholder="SRA-A-102" help="Deixe vazio quando não houver vínculo fixo." :error="$errors->first('propertyCode')" /><x-ui.select id="vehicle-relationship" label="Natureza do vínculo" wire:model="relationship" :error="$errors->first('relationship')" required><option value="proprietario">Pessoa e imóvel</option><option value="autorizado">Pessoa autorizada</option><option value="empresa">Empresa prestadora</option></x-ui.select><x-ui.select id="vehicle-access-use" label="Uso principal" wire:model="accessUse" :error="$errors->first('accessUse')" required><option value="morador">Morador</option><option value="visitante">Visitante</option><option value="prestador">Prestador</option><option value="administrativo">Administrativo</option></x-ui.select><x-ui.select id="vehicle-form-status" label="Situação do veículo" wire:model.live="vehicleStatus" :error="$errors->first('vehicleStatus')" required><option value="pendente">Pendente</option><option value="ativo">Ativo</option><option value="inativo">Inativo</option><option value="bloqueado">Bloqueado</option></x-ui.select></div></fieldset>

                <fieldset><legend>Observações</legend><label class="vehicle-notes-field" for="vehicle-notes"><span>Observações operacionais</span><textarea id="vehicle-notes" wire:model="notes" maxlength="300" rows="4" placeholder="Registre informações úteis para a conferência do veículo…"></textarea><small>{{ mb_strlen($notes) }}/300 caracteres</small></label></fieldset>

                <footer><x-ui.button variant="secondary" wire:click="backToList">Cancelar</x-ui.button><x-ui.button variant="warning" wire:click="saveDraft">Salvar rascunho</x-ui.button><x-ui.button type="submit" variant="success">Salvar veículo</x-ui.button></footer>
            </form>

            <aside class="vehicle-form-context">
                <x-ui.card title="O que acontece ao salvar?" description="O cadastro não abre nenhum acesso">
                    <ul><li><x-icon name="car" /><span><strong>Veículo</strong><small>Placa e características ficam registradas.</small></span></li><li><x-icon name="users" /><span><strong>Proprietário</strong><small>Nenhuma pessoa é criada ou alterada.</small></span></li><li><x-icon name="building" /><span><strong>Imóvel</strong><small>O vínculo é apenas demonstrativo nesta etapa.</small></span></li><li><x-icon name="shield" /><span><strong>Entrada</strong><small>A portaria ainda precisa validar cada acesso.</small></span></li></ul>
                </x-ui.card>
                <x-ui.alert variant="warning" title="Sem equipamento conectado">Nenhuma câmera, portão ou leitor de placa real está conectado a este cadastro.</x-ui.alert>
            </aside>
        </section>
    @endif
</div>
